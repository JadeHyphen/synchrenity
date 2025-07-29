<?php

declare(strict_types=1);

namespace Synchrenity\Auth;

use Synchrenity\Support\SynchrenityFacade;

class Auth extends SynchrenityFacade
{
    protected static $plugins = [];
    protected static $events  = [];
    protected static $metrics = [ 'calls' => 0, 'errors' => 0 ];
    protected static $context = [];
    protected static $macros  = [];

    protected static function getServiceName()
    {
        return 'auth';
    }

    // Plugin system
    public static function registerPlugin($plugin)
    {
        self::$plugins[] = $plugin;
    }
    // Event system
    public static function on($event, callable $cb)
    {
        self::$events[$event][] = $cb;
    }
    protected static function triggerEvent($event, $data = null)
    {
        foreach (self::$events[$event] ?? [] as $cb) {
            call_user_func($cb, $data, get_called_class());
        }
    }
    // Metrics
    public static function getMetrics()
    {
        return self::$metrics;
    }
    // Context
    public static function setContext($key, $value)
    {
        self::$context[$key] = $value;
    }
    public static function getContext($key, $default = null)
    {
        return self::$context[$key] ?? $default;
    }
    // Introspection
    public static function getPlugins()
    {
        return self::$plugins;
    }
    public static function getEvents()
    {
        return self::$events;
    }
    // Macroable
    public static function macro($name, callable $fn)
    {
        self::$macros[$name] = $fn;
    }
    public static function __callStatic($name, $arguments)
    {
        self::$metrics['calls']++;

        if (isset(self::$macros[$name])) {
            return call_user_func_array(self::$macros[$name], $arguments);
        }

        try {
            $service = static::resolveFacadeInstance(static::getServiceName());
            $result  = $service->$name(...$arguments);
            self::triggerEvent($name, $arguments);

            foreach (self::$plugins as $plugin) {
                if (is_callable([$plugin, 'onCall'])) {
                    $plugin->onCall($name, $arguments, $service);
                }
            }

            return $result;
        } catch (\Throwable $e) {
            self::$metrics['errors']++;
            self::triggerEvent('error', $e);

            foreach (self::$plugins as $plugin) {
                if (is_callable([$plugin, 'onError'])) {
                    $plugin->onError($e, $name, $arguments);
                }
            }

            throw $e;
        }
    }
}
