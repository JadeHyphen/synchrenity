<?php

declare(strict_types=1);

namespace Synchrenity\Queue;

class SynchrenityInMemoryJobQueueProvider implements SynchrenityJobQueueProviderInterface
{
    protected $queue           = [];
    protected $paused          = false;
    protected $deadLetterQueue = [];
    protected $dependencies    = [];
    protected $priorities      = [];
    protected $stats           = [ 'dispatched' => 0, 'completed' => 0, 'failed' => 0, 'retried' => 0 ];

    public function dispatch($job, $delay = 0, $retries = 0, $priority = 0, $dependencies = [])
    {
        // Ensure $priority and $dependencies are always set
        if (!isset($priority)) {
            $priority = 0;
        }

        if (!isset($dependencies)) {
            $dependencies = [];
        }
        $entry = [
            'id'           => uniqid('job_', true),
            'job'          => $job,
            'status'       => 'pending',
            'created'      => time(),
            'delay'        => $delay,
            'retries'      => $retries,
            'attempts'     => 0,
            'priority'     => $priority,
            'dependencies' => $dependencies,
            'result'       => null,
            'error'        => null,
        ];
        $this->queue[]                    = $entry;
        $this->priorities[$entry['id']]   = $priority;
        $this->dependencies[$entry['id']] = $dependencies;
        $this->stats['dispatched']++;
        $this->sortQueue();

        return $entry['id'];
    }

    public function process()
    {
        if ($this->paused) {
            return;
        }

        foreach ($this->queue as &$entry) {
            if ($entry['status'] !== 'pending') {
                continue;
            }

            if ($entry['delay'] > 0 && time() < $entry['created'] + $entry['delay']) {
                continue;
            }

            if (!$this->dependenciesMet($entry['id'])) {
                continue;
            }
            $entry['status'] = 'running';

            try {
                if (is_object($entry['job']) && method_exists($entry['job'], 'run')) {
                    $result = $entry['job']->run();
                } elseif (is_callable($entry['job'])) {
                    $result = call_user_func($entry['job']);
                } else {
                    $result = null;
                }
                $entry['status'] = 'completed';
                $entry['result'] = $result;
                $this->stats['completed']++;
            } catch (\Exception $ex) {
                $entry['attempts']++;

                if ($entry['attempts'] <= $entry['retries']) {
                    $entry['status'] = 'pending';
                    $this->stats['retried']++;
                } else {
                    $entry['status']         = 'failed';
                    $entry['error']          = $ex->getMessage();
                    $this->deadLetterQueue[] = $entry;
                    $this->stats['failed']++;
                }
            }
        }
    }

    public function getJobs($status = null)
    {
        if ($status) {
            return array_values(array_filter($this->queue, function ($j) use ($status) { return $j['status'] === $status; }));
        }

        return $this->queue;
    }

    public function getJob($jobId)
    {
        foreach ($this->queue as $entry) {
            if ($entry['id'] === $jobId) {
                return $entry;
            }
        }

        foreach ($this->deadLetterQueue as $entry) {
            if ($entry['id'] === $jobId) {
                return $entry;
            }
        }

        return null;
    }

    public function cancelJob($jobId)
    {
        foreach ($this->queue as &$entry) {
            if ($entry['id'] === $jobId) {
                $entry['status'] = 'cancelled';
                break;
            }
        }
    }

    public function pause()
    {
        $this->paused = true;
    }
    public function resume()
    {
        $this->paused = false;
    }
    public function isPaused()
    {
        return $this->paused;
    }

    // Advanced job management
    public function setPriority($jobId, $priority)
    {
        $this->priorities[$jobId] = $priority;

        foreach ($this->queue as &$entry) {
            if ($entry['id'] === $jobId) {
                $entry['priority'] = $priority;
                break;
            }
        }
        $this->sortQueue();
    }
    public function addDependency($jobId, $dependsOnJobId)
    {
        $this->dependencies[$jobId][] = $dependsOnJobId;

        foreach ($this->queue as &$entry) {
            if ($entry['id'] === $jobId) {
                $entry['dependencies'][] = $dependsOnJobId;
                break;
            }
        }
    }
    public function getDependencies($jobId)
    {
        return $this->dependencies[$jobId] ?? [];
    }
    public function getDeadLetterQueue()
    {
        return $this->deadLetterQueue;
    }
    public function retryJob($jobId)
    {
        foreach ($this->deadLetterQueue as $i => $entry) {
            if ($entry['id'] === $jobId) {
                $entry['status']   = 'pending';
                $entry['attempts'] = 0;
                $entry['error']    = null;
                $this->queue[]     = $entry;
                unset($this->deadLetterQueue[$i]);
                $this->sortQueue();
                break;
            }
        }
    }
    public function updateJob($jobId, $data)
    {
        foreach ($this->queue as &$entry) {
            if ($entry['id'] === $jobId) {
                foreach ($data as $k => $v) {
                    $entry[$k] = $v;
                }
                break;
            }
        }
    }
    public function jobExists($jobId)
    {
        foreach ($this->queue as $entry) {
            if ($entry['id'] === $jobId) {
                return true;
            }
        }

        foreach ($this->deadLetterQueue as $entry) {
            if ($entry['id'] === $jobId) {
                return true;
            }
        }

        return false;
    }
    public function getJobStatus($jobId)
    {
        $job = $this->getJob($jobId);

        return $job ? $job['status'] : null;
    }
    public function getJobResult($jobId)
    {
        $job = $this->getJob($jobId);

        return $job ? ($job['result'] ?? null) : null;
    }

    // Metrics and introspection
    public function getQueueLength($status = null)
    {
        if ($status) {
            return count($this->getJobs($status));
        }

        return count($this->queue);
    }
    public function getStats()
    {
        return $this->stats;
    }
    public function getNextScheduledJob()
    {
        $pending = $this->getJobs('pending');

        if (empty($pending)) {
            return null;
        }
        usort($pending, function ($a, $b) {
            return ($a['created'] + $a['delay']) <=> ($b['created'] + $b['delay']);
        });

        return $pending[0];
    }
    public function getFailedJobs()
    {
        return $this->getJobs('failed');
    }
    public function getCompletedJobs()
    {
        return $this->getJobs('completed');
    }

    // Helpers
    protected function dependenciesMet($jobId)
    {
        $deps = $this->dependencies[$jobId] ?? [];

        foreach ($deps as $depId) {
            $dep = $this->getJob($depId);

            if (!$dep || $dep['status'] !== 'completed') {
                return false;
            }
        }

        return true;
    }
    protected function sortQueue()
    {
        usort($this->queue, function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });
    }
}
