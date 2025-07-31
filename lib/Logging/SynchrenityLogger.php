<?php

declare(strict_types=1);
/**
 * SynchrenityLogger: Modern, extensible logging for Synchrenity
 * Supports: file, stdout, JSON, rotation, context, channels, and integration with Monolog if available.
 */

namespace Synchrenity\Logging;

class SynchrenityLogger
{
    protected string $logDir;
    protected string $channel;
    protected string $logLevel;
    protected bool $json;
    protected bool $stdout;
    protected $fileHandle;
    protected array $context = [];

    public const LEVELS = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

    public function __construct(array $options = [])
    {
        $this->logDir   = $options['log_dir'] ?? __DIR__ . '/../../storage/logs';
        $this->channel  = $options['channel'] ?? 'app';
        $this->logLevel = $options['level']   ?? 'debug';
        $this->json     = $options['json']    ?? true;
        $this->stdout   = $options['stdout']  ?? false;

        if (!is_dir($this->logDir)) {
            if (!mkdir($this->logDir, 0775, true) && !is_dir($this->logDir)) {
                throw new \RuntimeException("Failed to create log directory: {$this->logDir}");
            }
        }
    }

    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    public function log(string $level, string $message, array $context = []): void
    {
        if (!in_array($level, self::LEVELS)) {
            throw new \InvalidArgumentException("Invalid log level: $level");
        }

        if (array_search($level, self::LEVELS) < array_search($this->logLevel, self::LEVELS)) {
            return;
        }

        try {
            $entry = [
                'timestamp' => date('c'),
                'level'     => $level,
                'channel'   => $this->channel,
                'message'   => $message,
                'context'   => array_merge($this->context, $context),
                'pid'       => getmypid(),
            ];
            $line = $this->json ? json_encode($entry, JSON_UNESCAPED_SLASHES) :
                "[{$entry['timestamp']}] {$level}.{$this->channel}: {$message} " . json_encode($entry['context']);

            if ($this->stdout) {
                fwrite(STDOUT, $line . "\n");
            }
            $file = $this->logDir . '/' . $this->channel . '-' . date('Y-m-d') . '.log';
            
            if (file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX) === false) {
                throw new \RuntimeException("Failed to write to log file: $file");
            }
        } catch (\Throwable $e) {
            // Fallback to error_log if regular logging fails
            error_log("SynchrenityLogger error: " . $e->getMessage() . " | Original: [$level] $message");
        }
    }

    public function debug(string $msg, array $ctx = []): void
    {
        $this->log('debug', $msg, $ctx);
    }
    public function info(string $msg, array $ctx = []): void
    {
        $this->log('info', $msg, $ctx);
    }
    public function warning(string $msg, array $ctx = []): void
    {
        $this->log('warning', $msg, $ctx);
    }
    public function error(string $msg, array $ctx = []): void
    {
        $this->log('error', $msg, $ctx);
    }
    public function critical(string $msg, array $ctx = []): void
    {
        $this->log('critical', $msg, $ctx);
    }
    public function alert(string $msg, array $ctx = []): void
    {
        $this->log('alert', $msg, $ctx);
    }
    public function emergency($msg, $ctx = [])
    {
        $this->log('emergency', $msg, $ctx);
    }
}
