<?php

declare(strict_types=1);

namespace Synchrenity\Support;

/**
 * SynchrenityHealthCheck: Simple diagnostics/health check module
 * Usage: $health->status() returns array of health info
 */
class SynchrenityHealthCheck
{
    protected $core;

    public function __construct($core)
    {
        $this->core = $core;
    }

    /**
     * Returns an array of health diagnostics
     */
    public function status()
    {
        return [
            'app_env'           => getenv('APP_ENV') ?: 'production',
            'php_version'       => PHP_VERSION,
            'memory_usage'      => memory_get_usage(true),
            'loaded_extensions' => get_loaded_extensions(),
            'uptime'            => $this->getUptime(),
            'db_connected'      => $this->checkDb(),
            'cache_writable'    => $this->checkCache(),
            'time'              => date('c'),
        ];
    }

    protected function getUptime()
    {
        if (function_exists('posix_getpid') && file_exists('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');

            return trim(explode(' ', $uptime)[0]);
        }

        return null;
    }

    protected function checkDb()
    {
        if (method_exists($this->core, 'getModule')) {
            $db = $this->core->getModule('db');

            if ($db && method_exists($db, 'isConnected')) {
                return $db->isConnected();
            }
        }

        return null;
    }

    protected function checkCache()
    {
        $cacheDir = __DIR__ . '/../../storage/cache';

        if (!is_dir($cacheDir)) {
            return false;
        }

        return is_writable($cacheDir);
    }
}
