<?php

namespace Synchrenity\Queue;

use Synchrenity\Queue\SynchrenityJobQueueProviderInterface;

use PDO;

class SynchrenityDatabaseJobQueueProvider implements SynchrenityJobQueueProviderInterface {
    protected $pdo;
    protected $table;
    protected $paused = false;

    public function __construct(PDO $pdo, $table = 'synchrenity_jobqueue') {
        $this->pdo = $pdo;
        $this->table = $table;
        $this->initTable();
    }

    protected function initTable() {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$this->table} (
            id VARCHAR(64) PRIMARY KEY,
            job TEXT,
            status VARCHAR(32),
            created INT,
            delay INT,
            retries INT,
            attempts INT,
            result TEXT,
            error TEXT,
            priority INT DEFAULT 0,
            dependencies TEXT DEFAULT '[]'
        )");
    }

    public function dispatch($job, $delay = 0, $retries = 0, $priority = 0, $dependencies = []) {
        $id = uniqid('job_', true);
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (id, job, status, created, delay, retries, attempts, priority, dependencies) VALUES (?, ?, 'pending', ?, ?, ?, 0, ?, ?)");
        $stmt->execute([
            $id,
            serialize($job),
            time(),
            $delay,
            $retries,
            $priority,
            json_encode($dependencies)
        ]);
        return $id;
    }

    public function process() {
        if ($this->paused) return;
        $stmt = $this->pdo->query("SELECT * FROM {$this->table} WHERE status = 'pending'");
        while ($entry = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($entry['delay'] > 0 && time() < $entry['created'] + $entry['delay']) continue;
            $entry['status'] = 'running';
            $this->updateEntry($entry);
            try {
                $job = unserialize($entry['job']);
                if (is_object($job) && method_exists($job, 'run')) {
                    $result = $job->run();
                } elseif (is_callable($job)) {
                    $result = call_user_func($job);
                } else {
                    $result = null;
                }
                $entry['status'] = 'completed';
                $entry['result'] = serialize($result);
            } catch (\Exception $ex) {
                $entry['attempts']++;
                if ($entry['attempts'] <= $entry['retries']) {
                    $entry['status'] = 'pending';
                } else {
                    $entry['status'] = 'failed';
                    $entry['error'] = $ex->getMessage();
                }
            }
            $this->updateEntry($entry);
        }
    }

    protected function updateEntry($entry) {
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET status=?, attempts=?, result=?, error=? WHERE id=?");
        $stmt->execute([
            $entry['status'],
            $entry['attempts'],
            $entry['result'] ?? null,
            $entry['error'] ?? null,
            $entry['id']
        ]);
    }

    public function getJobs($status = null) {
        if ($status) {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE status = ?");
            $stmt->execute([$status]);
        } else {
            $stmt = $this->pdo->query("SELECT * FROM {$this->table}");
        }
        $jobs = [];
        while ($entry = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $entry['job'] = unserialize($entry['job']);
            $entry['result'] = isset($entry['result']) ? unserialize($entry['result']) : null;
            $jobs[] = $entry;
        }
        return $jobs;
    }

    public function cancelJob($jobId) {
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET status='cancelled' WHERE id=?");
        $stmt->execute([$jobId]);
    }

    public function pause() { $this->paused = true; }
    public function resume() { $this->paused = false; }
    public function isPaused() { return $this->paused; }

    // --- Robust interface: stubs for advanced methods ---
    // --- Fully implemented advanced interface methods ---
    public function getJob($jobId) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$jobId]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($entry) {
            $entry['job'] = unserialize($entry['job']);
            $entry['result'] = isset($entry['result']) ? unserialize($entry['result']) : null;
            $entry['dependencies'] = isset($entry['dependencies']) ? json_decode($entry['dependencies'], true) : [];
            return $entry;
        }
        return null;
    }
    public function setPriority($jobId, $priority) {
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET priority=? WHERE id=?");
        $stmt->execute([$priority, $jobId]);
    }
    public function addDependency($jobId, $dependsOnJobId) {
        $job = $this->getJob($jobId);
        $deps = $job ? $job['dependencies'] : [];
        $deps[] = $dependsOnJobId;
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET dependencies=? WHERE id=?");
        $stmt->execute([json_encode($deps), $jobId]);
    }
    public function getDependencies($jobId) {
        $job = $this->getJob($jobId);
        return $job && isset($job['dependencies']) ? $job['dependencies'] : [];
    }
    public function getDeadLetterQueue() {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table}_dead");
        $jobs = [];
        while ($entry = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $entry['job'] = unserialize($entry['job']);
            $entry['result'] = isset($entry['result']) ? unserialize($entry['result']) : null;
            $entry['dependencies'] = isset($entry['dependencies']) ? json_decode($entry['dependencies'], true) : [];
            $jobs[] = $entry;
        }
        return $jobs;
    }
    public function retryJob($jobId) {
        $deadTable = $this->table . '_dead';
        $stmt = $this->pdo->prepare("SELECT * FROM {$deadTable} WHERE id = ?");
        $stmt->execute([$jobId]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($entry) {
            $entry['status'] = 'pending';
            $entry['attempts'] = 0;
            $entry['error'] = null;
            $stmt2 = $this->pdo->prepare("INSERT INTO {$this->table} (id, job, status, created, delay, retries, attempts, result, error, priority, dependencies) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt2->execute([
                $entry['id'], $entry['job'], $entry['status'], $entry['created'], $entry['delay'], $entry['retries'], $entry['attempts'], $entry['result'], $entry['error'], $entry['priority'], $entry['dependencies']
            ]);
            $this->pdo->prepare("DELETE FROM {$deadTable} WHERE id = ?")->execute([$jobId]);
        }
    }
    public function updateJob($jobId, $data) {
        $fields = [];
        $values = [];
        foreach ($data as $k => $v) {
            $fields[] = "$k=?";
            $values[] = $k === 'job' ? serialize($v) : ($k === 'dependencies' ? json_encode($v) : $v);
        }
        $values[] = $jobId;
        $sql = "UPDATE {$this->table} SET ".implode(',', $fields)." WHERE id=?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
    }
    public function jobExists($jobId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->table} WHERE id=?");
        $stmt->execute([$jobId]);
        return $stmt->fetchColumn() > 0;
    }
    public function getJobStatus($jobId) {
        $job = $this->getJob($jobId);
        return $job ? $job['status'] : null;
    }
    public function getJobResult($jobId) {
        $job = $this->getJob($jobId);
        return $job ? ($job['result'] ?? null) : null;
    }
    public function getQueueLength($status = null) {
        if ($status) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->table} WHERE status=?");
            $stmt->execute([$status]);
            return (int)$stmt->fetchColumn();
        }
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$this->table}");
        return (int)$stmt->fetchColumn();
    }
    public function getStats() {
        $statuses = ['pending','running','completed','failed','cancelled'];
        $stats = [];
        foreach ($statuses as $s) {
            $stats[$s] = $this->getQueueLength($s);
        }
        return $stats;
    }
    public function getNextScheduledJob() {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table} WHERE status='pending' ORDER BY (created+delay) ASC LIMIT 1");
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($entry) {
            $entry['job'] = unserialize($entry['job']);
            $entry['result'] = isset($entry['result']) ? unserialize($entry['result']) : null;
            $entry['dependencies'] = isset($entry['dependencies']) ? json_decode($entry['dependencies'], true) : [];
        }
        return $entry ?: null;
    }
    public function getFailedJobs() {
        return $this->getJobs('failed');
    }
    public function getCompletedJobs() {
        return $this->getJobs('completed');
    }
}
