<?php
namespace Synchrenity\Queue;

class SynchrenityJobQueue {
    protected $auditTrail;
    protected $backend = 'memory'; // memory, file
    protected $queue = [];
    protected $filePath;
    protected $hooks = [];

    public function __construct($backend = 'memory', $options = []) {
        $this->backend = $backend;
        if ($backend === 'file') {
            $this->filePath = $options['filePath'] ?? __DIR__ . '/queue.data';
            if (!file_exists($this->filePath)) file_put_contents($this->filePath, json_encode([]));
            $this->loadFileQueue();
        }
    }

    public function setAuditTrail($auditTrail) {
        $this->auditTrail = $auditTrail;
    }

    public function addHook(callable $hook) {
        $this->hooks[] = $hook;
    }

    // Dispatch a job (array/object)
    public function dispatch($job, $delay = 0, $retries = 0) {
        $entry = [
            'job' => $job,
            'status' => 'pending',
            'created' => time(),
            'delay' => $delay,
            'retries' => $retries,
            'attempts' => 0
        ];
        if ($this->backend === 'memory') {
            $this->queue[] = $entry;
        } elseif ($this->backend === 'file') {
            $this->queue[] = $entry;
            $this->saveFileQueue();
        }
        foreach ($this->hooks as $hook) {
            call_user_func($hook, $entry);
        }
        $this->audit('dispatch_job', null, $entry);
        return true;
    }

    // Process jobs (simulate async)
    public function process() {
        foreach ($this->queue as $i => &$entry) {
            if ($entry['status'] !== 'pending') continue;
            if ($entry['delay'] > 0 && time() < $entry['created'] + $entry['delay']) continue;
            $entry['status'] = 'running';
            $this->audit('start_job', null, $entry);
            try {
                // Actual job logic (stub: call 'run' if object)
                if (is_object($entry['job']) && method_exists($entry['job'], 'run')) {
                    $result = $entry['job']->run();
                } elseif (is_callable($entry['job'])) {
                    $result = call_user_func($entry['job']);
                } else {
                    $result = null;
                }
                $entry['status'] = 'completed';
                $entry['result'] = $result;
                $this->audit('complete_job', null, $entry);
            } catch (\Exception $ex) {
                $entry['attempts']++;
                if ($entry['attempts'] <= $entry['retries']) {
                    $entry['status'] = 'pending';
                } else {
                    $entry['status'] = 'failed';
                    $entry['error'] = $ex->getMessage();
                    $this->audit('fail_job', null, $entry);
                }
            }
        }
        if ($this->backend === 'file') {
            $this->saveFileQueue();
        }
    }

    // Get all jobs
    public function getJobs($status = null) {
        if ($status) {
            return array_filter($this->queue, function($j) use ($status) { return $j['status'] === $status; });
        }
        return $this->queue;
    }

    // Internal: load/save file queue
    protected function loadFileQueue() {
        $data = @file_get_contents($this->filePath);
        $this->queue = $data ? json_decode($data, true) : [];
    }
    protected function saveFileQueue() {
        file_put_contents($this->filePath, json_encode($this->queue));
    }

    // Audit helper
    protected function audit($action, $userId = null, $meta = []) {
        if ($this->auditTrail) {
            $this->auditTrail->log($action, [], $userId, $meta);
        }
    }
}
