<?php

declare(strict_types=1);
// lib/Support/SynchrenityLogger.php

namespace Synchrenity\Support;

/**
 * SynchrenityLogger: Robust, multi-channel logger with audit integration
 */
class SynchrenityLogger
{
    protected $channels       = [];
    protected $defaultChannel = 'app';
    protected $logDir;
    protected $auditTrail;

    public function __construct($logDir = null, $auditTrail = null)
    {
        $this->logDir = $logDir ?: __DIR__ . '/../../../storage/logs';

        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0770, true);
        }
        $this->auditTrail = $auditTrail;
        $this->channels   = ['app', 'security', 'audit', 'error'];
    }

    public function setAuditTrail($auditTrail)
    {
        $this->auditTrail = $auditTrail;
    }

    public function addChannel($name)
    {
        if (!in_array($name, $this->channels)) {
            $this->channels[] = $name;
        }
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        // Channel can be set via $context['channel'] or defaults to 'app'
        $channel = $context['channel'] ?? $this->defaultChannel;

        if (!in_array($channel, $this->channels)) {
            $this->addChannel($channel);
        }
        $date = date('Y-m-d');
        $file = $this->logDir . "/{$channel}-{$date}.log";
        $meta = [
            'timestamp'  => date('c'),
            'level'      => $level,
            'ip'         => $_SERVER['REMOTE_ADDR']     ?? 'cli',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'cli',
            'context'    => $context,
        ];
        $entry = json_encode(['message' => (string)$message, 'meta' => $meta]) . "\n";
        file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);

        // Audit integration
        if ($this->auditTrail && method_exists($this->auditTrail, 'log')) {
            $this->auditTrail->log('logger.' . $channel, [
                'level'   => $level,
                'message' => (string)$message,
                'context' => $context,
                'meta'    => $meta,
            ]);
        }
    }

    // PSR-3 methods
    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }
    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }
    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }
    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }
    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }
    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }
    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }
    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }
}
