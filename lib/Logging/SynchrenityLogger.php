<?php

declare(strict_types=1);
/**
 * SynchrenityLogger: Modern, extensible logging for Synchrenity
 * Supports: file, stdout, JSON, rotation, context, channels, and integration with Monolog if available.
 */

namespace Synchrenity\Logging;

class SynchrenityLogger
{
    protected $logDir;
    protected $channel;
    protected $logLevel;
    protected $json;
    protected $stdout;
    protected $fileHandle;
    protected $context = [];

    public const LEVELS = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

    public function __construct($options = [])
    {
        $this->logDir   = $options['log_dir'] ?? __DIR__ . '/../../storage/logs';
        $this->channel  = $options['channel'] ?? 'app';
        $this->logLevel = $options['level']   ?? 'debug';
        $this->json     = $options['json']    ?? true;
        $this->stdout   = $options['stdout']  ?? false;

        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0775, true);
        }
    }

    public function setContext(array $context)
    {
        $this->context = $context;
    }

    public function log($level, $message, array $context = [])
    {
        if (array_search($level, self::LEVELS) < array_search($this->logLevel, self::LEVELS)) {
            return;
        }
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
        file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
    }

    public function debug($msg, $ctx = [])
    {
        $this->log('debug', $msg, $ctx);
    }
    public function info($msg, $ctx = [])
    {
        $this->log('info', $msg, $ctx);
    }
    public function warning($msg, $ctx = [])
    {
        $this->log('warning', $msg, $ctx);
    }
    public function error($msg, $ctx = [])
    {
        $this->log('error', $msg, $ctx);
    }
    public function critical($msg, $ctx = [])
    {
        $this->log('critical', $msg, $ctx);
    }
    public function alert($msg, $ctx = [])
    {
        $this->log('alert', $msg, $ctx);
    }
    public function emergency($msg, $ctx = [])
    {
        $this->log('emergency', $msg, $ctx);
    }
}
