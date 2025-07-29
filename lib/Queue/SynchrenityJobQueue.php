<?php
namespace Synchrenity\Queue;

class SynchrenityJobQueue {
    // --- ADVANCED: Job progress tracking ---
    protected $progress = [];
    public function setJobProgress($jobId, $percent, $message = null) {
        $this->progress[$jobId] = ['percent'=>$percent, 'message'=>$message, 'time'=>time()];
    }
    public function getJobProgress($jobId) {
        return $this->progress[$jobId] ?? null;
    }

    // --- ADVANCED: Job timeouts and auto-kill ---
    protected $timeouts = [];
    public function setJobTimeout($jobId, $seconds) { $this->timeouts[$jobId] = $seconds; }
    protected function checkTimeout($entry) {
        $id = $entry['id'];
        if (isset($this->timeouts[$id]) && $entry['status'] === 'running') {
            $start = $entry['started_at'] ?? $entry['created'];
            if (time() > $start + $this->timeouts[$id]) {
                $entry['status'] = 'timeout';
                $entry['error'] = 'Job timed out';
                $this->deadLetterQueue[] = $entry;
                $this->audit('timeout_job', null, $entry);
                $this->streamMetrics('timeout', $entry);
                return true;
            }
        }
        return false;
    }

    // --- ADVANCED: Job result retrieval by ID ---
    public function getJobResult($jobId) {
        $job = $this->findJobById($jobId);
        return $job['result'] ?? null;
    }

    // --- ADVANCED: Job search/filtering ---
    public function searchJobs($filter) {
        return array_filter($this->queue, function($j) use ($filter) {
            foreach ($filter as $k=>$v) if (!isset($j[$k]) || $j[$k] != $v) return false;
            return true;
        });
    }

    // --- ADVANCED: Job tagging and metadata ---
    protected $tags = [];
    public function tagJob($jobId, $tag) { $this->tags[$jobId][] = $tag; }
    public function getJobTags($jobId) { return $this->tags[$jobId] ?? []; }
    protected $metadata = [];
    public function setJobMetadata($jobId, $meta) { $this->metadata[$jobId] = $meta; }
    public function getJobMetadata($jobId) { return $this->metadata[$jobId] ?? []; }

    // --- ADVANCED: Job retry policies ---
    protected $retryPolicies = [];
    public function setRetryPolicy($jobId, callable $policy) { $this->retryPolicies[$jobId] = $policy; }
    protected function shouldRetry($entry) {
        $id = $entry['id'];
        if (isset($this->retryPolicies[$id])) return call_user_func($this->retryPolicies[$id], $entry);
        return $entry['attempts'] <= $entry['retries'];
    }

    // --- ADVANCED: Job expiration/TTL ---
    protected $expirations = [];
    public function setJobExpiration($jobId, $seconds) { $this->expirations[$jobId] = time() + $seconds; }
    protected function isExpired($entry) {
        $id = $entry['id'];
        return isset($this->expirations[$id]) && time() > $this->expirations[$id];
    }

    // --- ADVANCED: Multi-queue support ---
    protected $queues = ['default'];
    public function addQueue($name) { if (!in_array($name, $this->queues)) $this->queues[] = $name; }
    public function getQueues() { return $this->queues; }
    public function dispatchToQueue($queue, $job, $delay = 0, $retries = 0) {
        // For demo, just tag the job with queue name
        $entry = $this->dispatch($job, $delay, $retries);
        $this->tagJob($entry, $queue);
        return $entry;
    }

    // --- ADVANCED: Rate limiting/throttling ---
    protected $rateLimits = [];
    public function setRateLimit($queue, $limit, $window) { $this->rateLimits[$queue] = ['limit'=>$limit, 'window'=>$window, 'times'=>[]]; }
    protected function checkRateLimit($queue) {
        if (!isset($this->rateLimits[$queue])) return true;
        $now = time();
        $conf = $this->rateLimits[$queue];
        $conf['times'] = array_filter($conf['times'], function($t) use ($now, $conf) { return $t > $now - $conf['window']; });
        if (count($conf['times']) >= $conf['limit']) return false;
        $conf['times'][] = $now;
        $this->rateLimits[$queue] = $conf;
        return true;
    }

    // --- ADVANCED: Job event webhooks ---
    protected $webhooks = [];
    public function addWebhook($event, callable $cb) { $this->webhooks[$event][] = $cb; }
    protected function triggerWebhook($event, $entry) {
        foreach ($this->webhooks[$event] ?? [] as $cb) call_user_func($cb, $entry, $this);
    }

    // --- ADVANCED: Job chaining and workflows ---
    protected $chains = [];
    public function chainJobs(array $jobIds) { $chainId = uniqid('chain_', true); $this->chains[$chainId] = $jobIds; return $chainId; }
    public function getChain($chainId) { return $this->chains[$chainId] ?? []; }

    // --- ADVANCED: Per-job pausing/resuming ---
    protected $pausedJobs = [];
    public function pauseJob($jobId) { $this->pausedJobs[$jobId] = true; }
    public function resumeJob($jobId) { unset($this->pausedJobs[$jobId]); }
    public function isJobPaused($jobId) { return !empty($this->pausedJobs[$jobId]); }

    // --- ADVANCED: Job owner/actor tracking ---
    protected $owners = [];
    public function setJobOwner($jobId, $owner) { $this->owners[$jobId] = $owner; }
    public function getJobOwner($jobId) { return $this->owners[$jobId] ?? null; }

    // --- ADVANCED: Job output streaming/logging ---
    protected $outputLogs = [];
    public function appendJobOutput($jobId, $output) { $this->outputLogs[$jobId][] = ['output'=>$output, 'time'=>time()]; }
    public function getJobOutput($jobId) { return $this->outputLogs[$jobId] ?? []; }

    // --- ADVANCED: Introspection APIs ---
    public function getJobStatus($jobId) { $job = $this->findJobById($jobId); return $job ? $job['status'] : null; }
    public function getJob($jobId) { return $this->findJobById($jobId); }
    public function getFailedJobs() { return $this->getJobs('failed'); }
    public function getCompletedJobs() { return $this->getJobs('completed'); }
    public function getNextScheduledJob() {
        $pending = $this->getJobs('pending');
        if (empty($pending)) return null;
        usort($pending, function($a, $b) {
            return ($a['created'] + $a['delay']) <=> ($b['created'] + $b['delay']);
        });
        return $pending[0];
    }
    public function getQueueLength($status = null) { return $status ? count($this->getJobs($status)) : count($this->queue); }
    public function getStats() {
        $statuses = ['pending','running','completed','failed','cancelled','anomaly','timeout'];
        $stats = [];
        foreach ($statuses as $s) $stats[$s] = $this->getQueueLength($s);
        return $stats;
    }
    // --- ADVANCED: Distributed/clustered backend (Redis/DB/Custom) ---
    protected $distributedBackend = null;
    public function setDistributedBackend($backend) { $this->distributedBackend = $backend; }

    // --- ADVANCED: Priority queueing ---
    protected $priorityEnabled = false;
    public function enablePriority($enable = true) { $this->priorityEnabled = $enable; }

    // --- ADVANCED: Job dependencies ---
    protected $dependencies = [];
    public function addDependency($jobId, $dependsOn) { $this->dependencies[$jobId][] = $dependsOn; }
    protected function dependenciesMet($jobId) {
        if (!isset($this->dependencies[$jobId])) return true;
        foreach ($this->dependencies[$jobId] as $dep) {
            $job = $this->findJobById($dep);
            if (!$job || $job['status'] !== 'completed') return false;
        }
        return true;
    }

    // --- ADVANCED: Cron/recurring jobs ---
    protected $cronJobs = [];
    public function addCronJob($job, $cronExpr) {
        $this->cronJobs[] = ['job'=>$job, 'cron'=>$cronExpr, 'lastRun'=>0];
    }
    protected function shouldRunCron($cronExpr, $lastRun) {
        // Simple: run every N seconds (e.g. '60' means every minute)
        if (is_numeric($cronExpr)) return (time() - $lastRun) >= (int)$cronExpr;
        // TODO: Add full cron parser if needed
        return false;
    }

    // --- ADVANCED: Job cancellation, pausing, resuming ---
    protected $cancelled = [];
    protected $paused = false;
    public function cancelJob($jobId) { $this->cancelled[$jobId] = true; }
    public function pause() { $this->paused = true; }
    public function resume() { $this->paused = false; }
    public function isPaused() { return $this->paused; }

    // --- ADVANCED: Exponential backoff ---
    protected $backoff = [];
    public function setBackoff($jobId, $seconds) { $this->backoff[$jobId] = $seconds; }
    protected function getBackoff($jobId, $attempts) {
        $base = $this->backoff[$jobId] ?? 1;
        return $base * pow(2, $attempts-1);
    }

    // --- ADVANCED: Dead-letter queue ---
    protected $deadLetterQueue = [];
    public function getDeadLetterQueue() { return $this->deadLetterQueue; }

    // --- ADVANCED: Real-time metrics streaming ---
    protected $metricsStreamers = [];
    public function addMetricsStreamer(callable $cb) { $this->metricsStreamers[] = $cb; }
    protected function streamMetrics($event, $meta = []) {
        foreach ($this->metricsStreamers as $cb) {
            call_user_func($cb, $event, $meta, $this);
        }
    }

    // --- ADVANCED: Plugin-based extensibility ---
    protected $plugins = [];
    public function registerPlugin($plugin) {
        if (is_callable([$plugin, 'register'])) $plugin->register($this);
        $this->plugins[] = $plugin;
    }

    // --- ADVANCED: AI/ML anomaly detection (pluggable) ---
    protected $anomalyDetector = null;
    public function setAnomalyDetector(callable $detector) { $this->anomalyDetector = $detector; }
    protected function detectAnomaly($job, $meta = []) {
        if ($this->anomalyDetector) {
            return call_user_func($this->anomalyDetector, $job, $meta, $this);
        }
        return false;
    }

    // --- Internal: Find job by ID ---
    protected function findJobById($jobId) {
        foreach ($this->queue as $j) if (isset($j['id']) && $j['id'] === $jobId) return $j;
        return null;
    }
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
            'id' => uniqid('job_', true),
            'job' => $job,
            'status' => 'pending',
            'created' => time(),
            'delay' => $delay,
            'retries' => $retries,
            'attempts' => 0,
            'priority' => is_array($job) && isset($job['priority']) ? $job['priority'] : 0
        ];
        if ($this->priorityEnabled) {
            $this->queue[] = $entry;
            usort($this->queue, function($a, $b) { return $b['priority'] <=> $a['priority']; });
        } else {
            $this->queue[] = $entry;
        }
        if ($this->backend === 'file') {
            $this->saveFileQueue();
        }
        foreach ($this->hooks as $hook) {
            call_user_func($hook, $entry);
        }
        $this->audit('dispatch_job', null, $entry);
        $this->streamMetrics('dispatch', $entry);
        return $entry['id'];
    }

    // Process jobs (simulate async)
    public function process() {
        if ($this->isPaused()) return;
        // --- Cron/recurring jobs ---
        foreach ($this->cronJobs as &$cron) {
            if ($this->shouldRunCron($cron['cron'], $cron['lastRun'])) {
                $this->dispatch($cron['job']);
                $cron['lastRun'] = time();
            }
        }
        // --- Distributed backend ---
        if ($this->distributedBackend) {
            $this->distributedBackend->process($this);
            return;
        }
        foreach ($this->queue as $i => &$entry) {
            if ($entry['status'] !== 'pending') continue;
            if (isset($this->cancelled[$entry['id']])) {
                $entry['status'] = 'cancelled';
                $this->audit('cancel_job', null, $entry);
                $this->streamMetrics('cancel', $entry);
                continue;
            }
            if (!$this->dependenciesMet($entry['id'])) continue;
            if ($entry['delay'] > 0 && time() < $entry['created'] + $entry['delay']) continue;
            // Exponential backoff
            if ($entry['attempts'] > 0) {
                $backoff = $this->getBackoff($entry['id'], $entry['attempts']);
                if (time() < $entry['created'] + $backoff) continue;
            }
            // AI/ML anomaly detection
            if ($this->detectAnomaly($entry['job'], $entry)) {
                $entry['status'] = 'anomaly';
                $this->audit('anomaly_job', null, $entry);
                $this->streamMetrics('anomaly', $entry);
                continue;
            }
            $entry['status'] = 'running';
            $this->audit('start_job', null, $entry);
            $this->streamMetrics('start', $entry);
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
                $this->streamMetrics('complete', $entry);
            } catch (\Exception $ex) {
                $entry['attempts']++;
                if ($entry['attempts'] <= $entry['retries']) {
                    $entry['status'] = 'pending';
                    $this->audit('retry_job', null, $entry);
                    $this->streamMetrics('retry', $entry);
                } else {
                    $entry['status'] = 'failed';
                    $entry['error'] = $ex->getMessage();
                    $this->deadLetterQueue[] = $entry;
                    $this->audit('fail_job', null, $entry);
                    $this->streamMetrics('fail', $entry);
                }
            }
            // Plugin hooks (post-process)
            foreach ($this->plugins as $plugin) {
                if (is_callable([$plugin, 'afterProcess'])) {
                    $plugin->afterProcess($entry, $this);
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
