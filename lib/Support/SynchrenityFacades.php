<?php

declare(strict_types=1);

namespace Synchrenity\Support;

/**
 * Base Facade class for Laravel-style static access to services
 */
abstract class SynchrenityFacade
{
    protected static array $resolvedInstances = [];
    protected static ?SynchrenityServiceContainer $container = null;

    /**
     * Set the service container instance
     */
    public static function setContainer(SynchrenityServiceContainer $container): void
    {
        static::$container = $container;
    }

    /**
     * Get the service container instance
     */
    public static function getContainer(): SynchrenityServiceContainer
    {
        if (static::$container === null) {
            static::$container = $GLOBALS['synchrenityContainer'] ?? new SynchrenityServiceContainer();
        }
        return static::$container;
    }

    /**
     * Get the registered name of the component
     */
    abstract protected static function getFacadeAccessor(): string;

    /**
     * Resolve the facade root instance from the container
     */
    protected static function resolveFacadeInstance(string $name)
    {
        if (isset(static::$resolvedInstances[$name])) {
            return static::$resolvedInstances[$name];
        }

        $container = static::getContainer();
        
        if ($container->has($name)) {
            return static::$resolvedInstances[$name] = $container->get($name);
        }

        throw new \RuntimeException("Facade accessor '{$name}' is not bound in the container.");
    }

    /**
     * Clear a resolved facade instance
     */
    public static function clearResolvedInstance(string $name): void
    {
        unset(static::$resolvedInstances[$name]);
    }

    /**
     * Clear all resolved instances
     */
    public static function clearResolvedInstances(): void
    {
        static::$resolvedInstances = [];
    }

    /**
     * Handle dynamic, static calls to the object
     */
    public static function __callStatic(string $method, array $args)
    {
        $instance = static::resolveFacadeInstance(static::getFacadeAccessor());

        if (!$instance) {
            throw new \RuntimeException('A facade root has not been set.');
        }

        return $instance->$method(...$args);
    }
}

/**
 * Auth Facade
 */
class Auth extends SynchrenityFacade
{
    protected static function getFacadeAccessor(): string
    {
        return 'auth';
    }
}

/**
 * Cache Facade
 */
class Cache extends SynchrenityFacade
{
    protected static function getFacadeAccessor(): string
    {
        return 'cache';
    }
}

/**
 * Config Facade
 */
class Config extends SynchrenityFacade
{
    protected static function getFacadeAccessor(): string
    {
        return 'config';
    }
}

/**
 * Log Facade
 */
class Log extends SynchrenityFacade
{
    protected static function getFacadeAccessor(): string
    {
        return 'logger';
    }
}

/**
 * Security Facade
 */
class Security extends SynchrenityFacade
{
    protected static function getFacadeAccessor(): string
    {
        return 'security';
    }
}

/**
 * Session Facade
 */
class Session extends SynchrenityFacade
{
    protected static function getFacadeAccessor(): string
    {
        return 'session';
    }
}

/**
 * Router Facade
 */
class Router extends SynchrenityFacade
{
    protected static function getFacadeAccessor(): string
    {
        return 'router';
    }
}

/**
 * Response Facade
 */
class Response extends SynchrenityFacade
{
    protected static function getFacadeAccessor(): string
    {
        return 'response';
    }
}