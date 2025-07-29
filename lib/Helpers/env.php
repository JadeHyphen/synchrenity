<?php

declare(strict_types=1);

// Synchrenity Environment Loader
// Provides synchrenity_env() and env() helpers for robust .env support
if (!function_exists('synchrenity_env')) {
    /**
     * Load and parse .env file into an array (atomic, secure, robust, extensible)
     * Supports variable expansion, type casting, multi-line values, plugins, events, metrics, context, caching, error handling
     */
    function synchrenity_env($file, $options = [])
    {
        static $cache   = [];
        static $plugins = [];
        static $events  = [];
        static $metrics = [
            'loads'      => 0,
            'errors'     => 0,
            'expansions' => 0,
        ];
        static $context = [];
        $hash           = md5($file . serialize($options));

        if (isset($cache[$hash])) {
            return $cache[$hash];
        }
        $env   = [];
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            $metrics['errors']++;
            synchrenity_env_trigger('error', ['file' => $file, 'reason' => 'not_found']);

            return $env;
        }
        $metrics['loads']++;
        $multilineKey   = null;
        $multilineValue = '';

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            if ($multilineKey) {
                $multilineValue .= "\n" . $line;

                if (substr($line, -1) === '"') {
                    $val                = trim($multilineValue, '"');
                    $val                = synchrenity_env_expand($val, $env, $metrics);
                    $env[$multilineKey] = $val;
                    $multilineKey       = null;
                    $multilineValue     = '';
                }
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($key, $val) = explode('=', $line, 2);
                $key             = trim($key);
                $val             = trim($val);

                if ($val && $val[0] === '"' && substr($val, -1) !== '"') {
                    $multilineKey   = $key;
                    $multilineValue = ltrim($val, '"');
                    continue;
                }

                if ($val && $val[0] === '"') {
                    $val = trim($val, '"');
                }
                $val = synchrenity_env_expand($val, $env, $metrics);

                if (strtolower($val) === 'true') {
                    $val = true;
                } elseif (strtolower($val) === 'false') {
                    $val = false;
                } elseif (strtolower($val) === 'null') {
                    $val = null;
                } elseif (is_numeric($val)) {
                    $val = $val + 0;
                }
                $env[$key] = $val;
            }
        }
        $cache[$hash] = $env;
        synchrenity_env_trigger('load', ['file' => $file, 'env' => $env]);

        foreach ($plugins as $plugin) {
            if (is_callable([$plugin, 'onLoad'])) {
                $plugin->onLoad($env, $file, $options);
            }
        }

        return $env;
    }

    // Plugin system
    function synchrenity_env_register_plugin($plugin)
    {
        static $plugins = [];
        $plugins[]      = $plugin;
    }
    // Event system
    function synchrenity_env_on($event, callable $cb)
    {
        static $events    = [];
        $events[$event][] = $cb;
    }
    function synchrenity_env_trigger($event, $data = null)
    {
        static $events = [];

        foreach ($events[$event] ?? [] as $cb) {
            call_user_func($cb, $data);
        }
    }
    // Context
    function synchrenity_env_set_context($key, $value)
    {
        static $context = [];
        $context[$key]  = $value;
    }
    function synchrenity_env_get_context($key, $default = null)
    {
        static $context = [];

        return $context[$key] ?? $default;
    }
    // Metrics
    function synchrenity_env_metrics()
    {
        static $metrics = [];

        return $metrics;
    }
    // Introspection
    function synchrenity_env_plugins()
    {
        static $plugins = [];

        return $plugins;
    }
    function synchrenity_env_events()
    {
        static $events = [];

        return $events;
    }
    // Advanced variable expansion
    function synchrenity_env_expand($val, $env, &$metrics)
    {
        $metrics['expansions']++;

        return preg_replace_callback('/\${([A-Z0-9_]+)}/', function ($m) use ($env) {
            return $env[$m[1]] ?? getenv($m[1]) ?? '';
        }, $val);
    }
}

if (!function_exists('env')) {
    /**
     * Gets the value of an environment variable or returns a default value.
     * Supports type casting for booleans, null, int, float.
     */
    function env($key, $default = null)
    {
        $value = getenv($key);

        if ($value === false) {
            return $default;
        }

        if (is_string($value)) {
            $lower = strtolower($value);

            if ($lower === 'true' || $lower === '(true)') {
                return true;
            }

            if ($lower === 'false' || $lower === '(false)') {
                return false;
            }

            if ($lower === 'null' || $lower === '(null)') {
                return null;
            }

            if (is_numeric($value)) {
                if (strpos($value, '.') !== false) {
                    return (float)$value;
                }

                return (int)$value;
            }
        }

        return $value;
    }
}
