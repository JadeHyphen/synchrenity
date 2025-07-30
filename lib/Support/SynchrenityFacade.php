<?php

declare(strict_types=1);

namespace Synchrenity\Support;

use Psr\Container\ContainerInterface;

/**
 * Class SynchrenityFacade
 *
 * A robust, extensible static facade for Synchrenity services.
 * Supports macros, event hooks, context, and type-safe container access.
 */
abstract class SynchrenityFacade
{
    /**
     * @var ContainerInterface|null
     */
    protected static ?ContainerInterface $container = null;

    /**
     * @var array<string, callable> Macros registered for this facade
     */
    protected static array $macros = [];

    /**
     * @var array<string, array<int, callable>> Event hooks (before/after)
     */
    protected static array $eventHooks = [
        'before' => [],
        'after' => [],
    ];

    /**
     * @var mixed Optional context for advanced use cases
     */
    protected static $context = null;

    /**
     * Set the service container.
     * @param ContainerInterface $container
     */
    public static function setContainer(ContainerInterface $container): void
    {
        static::$container = $container;
    }

    /**
     * Set a context value for the facade (optional).
     * @param mixed $context
     */
    public static function setContext($context): void
    {
        static::$context = $context;
    }

    /**
     * Register a macro (dynamic static method).
     * @param string $name
     * @param callable $macro
     */
    public static function macro(string $name, callable $macro): void
    {
        static::$macros[$name] = $macro;
    }

    /**
     * Register an event hook (before/after method call).
     * @param string $when 'before' or 'after'
     * @param callable $hook function($method, $args, $service, $result|null)
     */
    public static function hook(string $when, callable $hook): void
    {
        if (!isset(static::$eventHooks[$when])) {
            throw new \InvalidArgumentException('Invalid hook type: ' . $when);
        }
        static::$eventHooks[$when][] = $hook;
    }

    /**
     * Handle dynamic static calls to the underlying service or macro.
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        // Macro support
        if (isset(static::$macros[$method])) {
            return \call_user_func_array(static::$macros[$method], $args);
        }

        // Container check
        if (!static::$container) {
            throw new \RuntimeException('Facade container is not set.');
        }

        $serviceName = static::getServiceName();
        if (!static::$container->has($serviceName)) {
            throw new \RuntimeException("Service '{$serviceName}' not found in container.");
        }

        $service = static::$container->get($serviceName);

        // Before hooks
        foreach (static::$eventHooks['before'] as $hook) {
            $hook($method, $args, $service, null);
        }

        if (!\method_exists($service, $method)) {
            throw new \BadMethodCallException("Method '{$method}' does not exist on service '{$serviceName}'.");
        }

        $result = $service->{$method}(...$args);

        // After hooks
        foreach (static::$eventHooks['after'] as $hook) {
            $hook($method, $args, $service, $result);
        }

        return $result;
    }

    /**
     * Get the service name for the facade.
     * @return string
     */
    abstract protected static function getServiceName(): string;
}
