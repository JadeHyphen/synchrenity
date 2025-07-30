<?php

declare(strict_types=1);

namespace Synchrenity\Auth;

use Synchrenity\Support\SynchrenityFacade;

/**
 * Auth Facade
 *
 * Provides robust, extensible static access to the Auth service.
 * Supports plugins, events, metrics, context, and macros.
 */
class Auth extends SynchrenityFacade
{
    /** @var array Plugins registered for Auth */
    protected static array $plugins = [];
    /** @var array<string, array<int, callable>> Event callbacks */
    protected static array $events  = [];
    /** @var array<string, int> Metrics for calls and errors */
    protected static array $metrics = [ 'calls' => 0, 'errors' => 0 ];
    /** @var array Contextual data */
    protected static array $context = [];

    protected static function getServiceName(): string
    {
        return 'auth';
    }

    // Plugin system
    public static function registerPlugin($plugin): void
    {
        static::$plugins[] = $plugin;
    }
    // Event system
    public static function on(string $event, callable $cb): void
    {
        static::$events[$event][] = $cb;
    }
    protected static function triggerEvent(string $event, $data = null): void
    {
        foreach (static::$events[$event] ?? [] as $cb) {
            $cb($data, static::class);
        }
    }
    // Metrics
    public static function getMetrics(): array
    {
        return static::$metrics;
    }
    // Context
    /**
     * Set a context value for the Auth facade (keyed).
     * This does not override the SynchrenityFacade::setContext, but provides additional keyed context.
     * @param string $key
     * @param mixed $value
     */
    public static function setContextValue(string $key, $value): void
    {
        static::$context[$key] = $value;
    }
    /**
     * Get a context value for the Auth facade (keyed).
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getContextValue(string $key, $default = null)
    {
        return static::$context[$key] ?? $default;
    }
    // Introspection
    public static function getPlugins(): array
    {
        return static::$plugins;
    }
    public static function getEvents(): array
    {
        return static::$events;
    }
    // Macroable: use SynchrenityFacade's macro system
    // __callStatic override for plugin/event/metrics integration
    public static function __callStatic($name, $arguments)
    {
        static::$metrics['calls']++;

        // Macro support (from SynchrenityFacade)
        if (isset(static::$macros[$name])) {
            return \call_user_func_array(static::$macros[$name], $arguments);
        }

        try {
            // Use SynchrenityFacade's container logic
            if (!static::$container) {
                throw new \RuntimeException('Facade container is not set.');
            }
            $serviceName = static::getServiceName();
            if (!static::$container->has($serviceName)) {
                throw new \RuntimeException("Service '{$serviceName}' not found in container.");
            }
            $service = static::$container->get($serviceName);

            if (!\method_exists($service, $name)) {
                throw new \BadMethodCallException("Method '{$name}' does not exist on service '{$serviceName}'.");
            }

            $result = $service->{$name}(...$arguments);
            static::triggerEvent($name, $arguments);

            foreach (static::$plugins as $plugin) {
                if (is_callable([$plugin, 'onCall'])) {
                    $plugin->onCall($name, $arguments, $service);
                }
            }

            return $result;
        } catch (\Throwable $e) {
            static::$metrics['errors']++;
            static::triggerEvent('error', $e);

            foreach (static::$plugins as $plugin) {
                if (is_callable([$plugin, 'onError'])) {
                    $plugin->onError($e, $name, $arguments);
                }
            }

            throw $e;
        }
    }
}
