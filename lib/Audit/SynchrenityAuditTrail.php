<?php

declare(strict_types=1);

namespace Synchrenity\Audit;

class SynchrenityAuditTrail
{
    // Plugin/event/metrics/context/introspection system
    private $plugins = [];
    private $events  = [];
    private $metrics = [
        'logs'      => 0,
        'errors'    => 0,
        'alerts'    => 0,
        'anomalies' => 0,
        'forwards'  => 0,
    ];
    private $context = [];
    // Core properties
    private $logFile;
    private $db;
    private $alertCallback;
    private $retentionDays = 90;
    private $roleAccess    = [];

    // Advanced features
    private $encryptionKey;
    private $tenantId;
    private $geoProvider;
    private $customSchema = [];
    private $apiToken;
    private $immutable = false;

    public function __construct($logFile = null, $db = null)
    {
        $this->logFile = $logFile ?? __DIR__ . '/audit.log';
        $this->db      = $db;
    }

    // Feature setters
    public function setAlertCallback(callable $callback)
    {
        $this->alertCallback = $callback;
    }
    public function setRoleAccess(array $roles)
    {
        $this->roleAccess = $roles;
    }
    public function setCustomSchema(array $schema)
    {
        $this->customSchema = $schema;
    }
    public function setTenantId($tenantId)
    {
        $this->tenantId = $tenantId;
    }
    public function setEncryptionKey($key)
    {
        $this->encryptionKey = $key;
    }
    public function setGeoProvider(callable $provider)
    {
        $this->geoProvider = $provider;
    }
    public function setApiToken($token)
    {
        $this->apiToken = $token;
    }
    public function enableImmutableStorage($flag = true)
    {
        $this->immutable = $flag;
    }
    public function setRetentionDays($days)
    {
        $this->retentionDays = $days;
    }

    // Main log method
    public function log($action, $data, $userId = null, $meta = [])
    {
        $this->metrics['logs']++;
        $timestamp = date('Y-m-d H:i:s');
        $ip        = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $geo       = $this->geoProvider ? call_user_func($this->geoProvider, $ip) : null;
        $entry     = array_merge([
            'timestamp' => $timestamp,
            'action'    => $action,
            'user_id'   => $userId,
            'data'      => $data,
            'ip'        => $ip,
            'meta'      => $meta,
            'hash'      => $this->generateHash($action, $data, $timestamp, $userId),
            'tenant_id' => $this->tenantId,
            'geo'       => $geo,
        ], $this->customSchema);
        $record = $this->encryptionKey ? $this->encrypt(json_encode($entry)) : json_encode($entry);

        if ($this->immutable) {
            file_put_contents($this->logFile, $record . PHP_EOL, FILE_APPEND | LOCK_EX);
        } else {
            $this->writeToFile($entry);
        }

        if ($this->db) {
            $this->writeToDb($entry);
        }

        if ($this->alertCallback && in_array($action, ['delete_user', 'tamper_detected', 'critical_error'])) {
            $this->metrics['alerts']++;
            call_user_func($this->alertCallback, $entry);
        }
        $this->triggerEvent('log', $entry);

        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'onLog'])) {
                $plugin->onLog($entry, $this);
            }
        }
    }
    // Plugin system
    public function registerPlugin($plugin)
    {
        $this->plugins[] = $plugin;
    }
    // Event system
    public function on($event, callable $cb)
    {
        $this->events[$event][] = $cb;
    }
    private function triggerEvent($event, $data = null)
    {
        foreach ($this->events[$event] ?? [] as $cb) {
            call_user_func($cb, $data, $this);
        }
    }
    // Metrics
    public function getMetrics()
    {
        return $this->metrics;
    }
    // Context
    public function setContext($key, $value)
    {
        $this->context[$key] = $value;
    }
    public function getContext($key, $default = null)
    {
        return $this->context[$key] ?? $default;
    }
    // Introspection
    public function getPlugins()
    {
        return $this->plugins;
    }
    public function getEvents()
    {
        return $this->events;
    }
    // Advanced search
    public function search($query)
    {
        $logs = $this->getLogs(1000);

        return array_filter($logs, function ($log) use ($query) {
            return stripos(json_encode($log), $query) !== false;
        });
    }
    // Audit chain (hash chain for tamper-proofing)
    public function verifyChain()
    {
        $logs = $this->getLogs(1000);
        $prev = '';

        foreach ($logs as $log) {
            $expected = hash('sha256', ($prev ? $prev : '') . $log->hash);

            if (isset($log->chain) && $log->chain !== $expected) {
                return false;
            }
            $prev = $log->hash;
        }

        return true;
    }
    // Add chain hash to log entry (call in log())
    private function addChainHash(&$entry, $prevHash)
    {
        $entry['chain'] = hash('sha256', ($prevHash ? $prevHash : '') . $entry['hash']);
    }

    private function writeToFile($entry)
    {
        $line = json_encode($entry) . PHP_EOL;
        file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }

    private function writeToDb($entry)
    {
        $stmt = $this->db->prepare('INSERT INTO audit_trail (timestamp, action, user_id, data, ip, hash) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $entry['timestamp'],
            $entry['action'],
            $entry['user_id'],
            json_encode($entry['data']),
            $entry['ip'],
            $entry['hash'],
        ]);
    }

    private function encrypt($data)
    {
        if (!$this->encryptionKey) {
            return $data;
        }

        return openssl_encrypt($data, 'AES-256-CBC', $this->encryptionKey, 0, substr(hash('sha256', $this->encryptionKey), 0, 16));
    }

    private function decrypt($data)
    {
        if (!$this->encryptionKey) {
            return $data;
        }

        return openssl_decrypt($data, 'AES-256-CBC', $this->encryptionKey, 0, substr(hash('sha256', $this->encryptionKey), 0, 16));
    }

    public function getLogs($limit = 100)
    {
        $lines  = @file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $logs   = array_slice(array_reverse($lines), 0, $limit);
        $result = [];

        foreach ($logs as $line) {
            $data     = $this->encryptionKey ? $this->decrypt($line) : $line;
            $result[] = json_decode($data);
        }

        return $result;
    }

    public function filterLogs($criteria = [])
    {
        $logs = $this->getLogs(1000);

        return array_filter($logs, function ($log) use ($criteria) {
            foreach ($criteria as $key => $value) {
                if (!isset($log->$key) || $log->$key != $value) {
                    return false;
                }
            }

            return true;
        });
    }

    public function exportCSV($file = null)
    {
        $logs = $this->getLogs(1000);
        $file = $file ?? __DIR__ . '/audit_export.csv';
        $fp   = fopen($file, 'w');
        fputcsv($fp, ['timestamp','action','user_id','data','ip','meta','hash','tenant_id','geo']);

        foreach ($logs as $log) {
            fputcsv($fp, [
                $log->timestamp ?? '',
                $log->action    ?? '',
                $log->user_id   ?? '',
                json_encode($log->data ?? []),
                $log->ip ?? '',
                json_encode($log->meta ?? []),
                $log->hash      ?? '',
                $log->tenant_id ?? '',
                json_encode($log->geo ?? []),
            ]);
        }
        fclose($fp);

        return $file;
    }

    public function rotateLogs()
    {
        $lines   = @file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $cutoff  = strtotime('-' . $this->retentionDays . ' days');
        $keep    = [];
        $archive = [];

        foreach ($lines as $line) {
            $log = json_decode($line);
            $ts  = isset($log->timestamp) ? strtotime($log->timestamp) : false;

            if ($ts !== false && $ts >= $cutoff) {
                $keep[] = $line;
            } else {
                $archive[] = $line;
            }
        }
        file_put_contents($this->logFile, implode(PHP_EOL, $keep) . PHP_EOL);

        if ($archive) {
            file_put_contents($this->logFile . '.archive', implode(PHP_EOL, $archive) . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }

    public function scanForTampering()
    {
        $logs = $this->getLogs(1000);
        $bad  = [];

        foreach ($logs as $log) {
            if (!$this->verify((array)$log)) {
                $bad[] = $log;

                if ($this->alertCallback) {
                    call_user_func($this->alertCallback, $log);
                }
            }
        }

        return $bad;
    }

    public function streamEvent($entry, $destination)
    {
        // Example: send to Kafka, Redis, webhook, etc.
        // Implement integration as needed
    }

    public function purgeOldLogs()
    {
        $lines  = @file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $cutoff = strtotime('-' . $this->retentionDays . ' days');
        $keep   = [];

        foreach ($lines as $line) {
            $log = json_decode($line);

            if (strtotime($log->timestamp) >= $cutoff) {
                $keep[] = $line;
            }
        }
        file_put_contents($this->logFile, implode(PHP_EOL, $keep) . PHP_EOL);
    }

    public function dashboard()
    {
        return [
            'total_logs'    => count($this->getLogs(1000)),
            'recent'        => $this->getLogs(10),
            'tamper_events' => $this->scanForTampering(),
        ];
    }

    // Advanced features
    public function forwardLogs($destination)
    {
        // Example: send logs to syslog, ELK, Splunk, etc.
        // Implement integration as needed
    }

    public function generateComplianceReport($type = 'gdpr')
    {
        // Generate report based on type (GDPR, HIPAA, SOC2, etc.)
        // Implement logic as needed
        return "Compliance report for $type";
    }

    public function apiGetLogs($token, $criteria = [])
    {
        if ($token !== $this->apiToken) {
            return null;
        }

        return $this->filterLogs($criteria);
    }

    public function detectAnomalies()
    {
        // Integrate with ML/AI for suspicious pattern detection
        // Implement logic as needed
        return [];
    }

    public function enforceRetentionPolicy()
    {
        $this->purgeOldLogs();
        // Report purged logs, etc.
    }
    public function verify($entry)
    {
        $expected = $this->generateHash($entry['action'], $entry['data'], $entry['timestamp'], $entry['user_id']);

        return hash_equals($expected, $entry['hash']);
    }
    private function generateHash($action, $data, $timestamp, $userId)
    {
        return hash('sha256', $action . json_encode($data) . $timestamp . $userId . 'synchrenity_secret');
    }
}
