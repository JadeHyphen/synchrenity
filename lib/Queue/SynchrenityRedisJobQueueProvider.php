<?php

declare(strict_types=1);

namespace Synchrenity\Queue;

class SynchrenityRedisJobQueueProvider implements SynchrenityJobQueueProviderInterface
{
    protected $redis;
    protected $queueKey;
    protected $paused = false;

    /**
     * Accepts any Redis-like client (native, mock, or compatible)
     */
    public function __construct($redis, $queueKey = 'synchrenity:jobqueue')
    {
        if (!is_object($redis) || !method_exists($redis, 'rPush')) {
            throw new \InvalidArgumentException('Redis client must support rPush, lLen, lIndex, lSet');
        }
        $this->redis    = $redis;
        $this->queueKey = $queueKey;
    }

    public function dispatch($job, $delay = 0, $retries = 0, $priority = 0, $dependencies = [])
    {
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
        $this->redis->rPush($this->queueKey, json_encode($entry));

        return $entry['id'];
    }

    public function process()
    {
        if ($this->paused) {
            return;
        }
        $len = $this->redis->lLen($this->queueKey);

        for ($i = 0; $i < $len; $i++) {
            $raw   = $this->redis->lIndex($this->queueKey, $i);
            $entry = json_decode($raw, true);

            if ($entry['status'] !== 'pending') {
                continue;
            }

            if ($entry['delay'] > 0 && time() < $entry['created'] + $entry['delay']) {
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
            } catch (\Exception $ex) {
                $entry['attempts']++;

                if ($entry['attempts'] <= $entry['retries']) {
                    $entry['status'] = 'pending';
                } else {
                    $entry['status'] = 'failed';
                    $entry['error']  = $ex->getMessage();
                }
            }
            $this->redis->lSet($this->queueKey, $i, json_encode($entry));
        }
    }

    public function getJobs($status = null)
    {
        $jobs = [];
        $len  = $this->redis->lLen($this->queueKey);

        for ($i = 0; $i < $len; $i++) {
            $entry = json_decode($this->redis->lIndex($this->queueKey, $i), true);

            if ($status === null || $entry['status'] === $status) {
                $jobs[] = $entry;
            }
        }

        return $jobs;
    }

    public function cancelJob($jobId)
    {
        $len = $this->redis->lLen($this->queueKey);

        for ($i = 0; $i < $len; $i++) {
            $entry = json_decode($this->redis->lIndex($this->queueKey, $i), true);

            if ($entry['id'] === $jobId) {
                $entry['status'] = 'cancelled';
                $this->redis->lSet($this->queueKey, $i, json_encode($entry));
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
    // --- Robust interface: stubs for advanced methods ---
    // --- Fully implemented advanced interface methods ---
    protected function getAllJobsRaw()
    {
        $jobs = [];
        $len  = $this->redis->lLen($this->queueKey);

        for ($i = 0; $i < $len; $i++) {
            $entry  = json_decode($this->redis->lIndex($this->queueKey, $i), true);
            $jobs[] = $entry;
        }

        return $jobs;
    }

    public function getJob($jobId)
    {
        $len = $this->redis->lLen($this->queueKey);

        for ($i = 0; $i < $len; $i++) {
            $entry = json_decode($this->redis->lIndex($this->queueKey, $i), true);

            if ($entry['id'] === $jobId) {
                return $entry;
            }
        }

        return null;
    }
    public function setPriority($jobId, $priority)
    {
        $len = $this->redis->lLen($this->queueKey);

        for ($i = 0; $i < $len; $i++) {
            $entry = json_decode($this->redis->lIndex($this->queueKey, $i), true);

            if ($entry['id'] === $jobId) {
                $entry['priority'] = $priority;
                $this->redis->lSet($this->queueKey, $i, json_encode($entry));
                break;
            }
        }
    }
    public function addDependency($jobId, $dependsOnJobId)
    {
        $len = $this->redis->lLen($this->queueKey);

        for ($i = 0; $i < $len; $i++) {
            $entry = json_decode($this->redis->lIndex($this->queueKey, $i), true);

            if ($entry['id'] === $jobId) {
                if (!isset($entry['dependencies']) || !is_array($entry['dependencies'])) {
                    $entry['dependencies'] = [];
                }
                $entry['dependencies'][] = $dependsOnJobId;
                $this->redis->lSet($this->queueKey, $i, json_encode($entry));
                break;
            }
        }
    }
    public function getDependencies($jobId)
    {
        $job = $this->getJob($jobId);

        return $job && isset($job['dependencies']) ? $job['dependencies'] : [];
    }
    public function getDeadLetterQueue()
    {
        // For Redis, we can use a separate key for dead jobs
        $deadKey = $this->queueKey . ':dead';
        $jobs    = [];
        $len     = $this->redis->lLen($deadKey);

        for ($i = 0; $i < $len; $i++) {
            $entry  = json_decode($this->redis->lIndex($deadKey, $i), true);
            $jobs[] = $entry;
        }

        return $jobs;
    }
    public function retryJob($jobId)
    {
        $deadKey = $this->queueKey . ':dead';
        $len     = $this->redis->lLen($deadKey);

        for ($i = 0; $i < $len; $i++) {
            $entry = json_decode($this->redis->lIndex($deadKey, $i), true);

            if ($entry['id'] === $jobId) {
                $entry['status']   = 'pending';
                $entry['attempts'] = 0;
                $entry['error']    = null;
                $this->redis->rPush($this->queueKey, json_encode($entry));
                $this->redis->lSet($deadKey, $i, json_encode(['_deleted' => true]));
                break;
            }
        }
        // Remove all _deleted from dead queue
        $this->cleanDeadLetterQueue();
    }
    protected function cleanDeadLetterQueue()
    {
        $deadKey = $this->queueKey . ':dead';
        $len     = $this->redis->lLen($deadKey);
        $toKeep  = [];

        for ($i = 0; $i < $len; $i++) {
            $entry = json_decode($this->redis->lIndex($deadKey, $i), true);

            if (!isset($entry['_deleted'])) {
                $toKeep[] = $entry;
            }
        }
        $this->redis->del($deadKey);

        foreach ($toKeep as $entry) {
            $this->redis->rPush($deadKey, json_encode($entry));
        }
    }
    public function updateJob($jobId, $data)
    {
        $len = $this->redis->lLen($this->queueKey);

        for ($i = 0; $i < $len; $i++) {
            $entry = json_decode($this->redis->lIndex($this->queueKey, $i), true);

            if ($entry['id'] === $jobId) {
                foreach ($data as $k => $v) {
                    $entry[$k] = $v;
                }
                $this->redis->lSet($this->queueKey, $i, json_encode($entry));
                break;
            }
        }
    }
    public function jobExists($jobId)
    {
        return $this->getJob($jobId) !== null;
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
    public function getQueueLength($status = null)
    {
        if ($status) {
            return count($this->getJobs($status));
        }

        return $this->redis->lLen($this->queueKey);
    }
    public function getStats()
    {
        $all   = $this->getAllJobsRaw();
        $stats = [ 'pending' => 0, 'running' => 0, 'completed' => 0, 'failed' => 0, 'cancelled' => 0 ];

        foreach ($all as $j) {
            if (isset($j['status'])) {
                $stats[$j['status']] = ($stats[$j['status']] ?? 0) + 1;
            }
        }

        return $stats;
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
}
