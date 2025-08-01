<?php

declare(strict_types=1);

namespace Synchrenity\Support;

/**
 * Base Service Provider class for Laravel-style service registration
 */
abstract class SynchrenityServiceProvider
{
    protected SynchrenityServiceContainer $container;

    public function __construct(SynchrenityServiceContainer $container)
    {
        $this->container = $container;
    }

    /**
     * Register services in the container
     */
    abstract public function register(): void;

    /**
     * Boot services after all providers are registered
     */
    public function boot(): void
    {
        // Override in child classes if needed
    }

    /**
     * Get services provided by this provider
     */
    public function provides(): array
    {
        return [];
    }

    /**
     * Determine if the provider is deferred
     */
    public function isDeferred(): bool
    {
        return false;
    }
}

/**
 * Core Framework Service Provider
 */
class SynchrenityCoreServiceProvider extends SynchrenityServiceProvider
{
    public function register(): void
    {
        // Register core services
        $this->container->singleton('auth', function ($container) {
            return new \Synchrenity\Auth\SynchrenityAuth();
        });

        $this->container->singleton('cache', function ($container) {
            return new \Synchrenity\Cache\SynchrenityCacheManager();
        });

        $this->container->singleton('security', function ($container) {
            $config = $container->has('config') ? $container->get('config') : [];
            return new \Synchrenity\Security\SynchrenitySecurityManager($config);
        });

        $this->container->singleton('logger', function ($container) {
            $logDir = __DIR__ . '/../../storage/logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            return new \Synchrenity\Support\SynchrenityLogger($logDir . '/app.log');
        });

        $this->container->singleton('router', function ($container) {
            return new \Synchrenity\Http\SynchrenityRouter();
        });

        $this->container->singleton('response', function ($container) {
            return new \Synchrenity\Http\SynchrenityResponse();
        });
    }

    public function boot(): void
    {
        // Set up facades
        if (class_exists('Synchrenity\Support\Auth')) {
            \Synchrenity\Support\Auth::setContainer($this->container);
        }
    }

    public function provides(): array
    {
        return ['auth', 'cache', 'security', 'logger', 'router', 'response'];
    }
}

/**
 * Security Service Provider
 */
class SynchrenitySecurityServiceProvider extends SynchrenityServiceProvider
{
    public function register(): void
    {
        $this->container->singleton('csrf', function ($container) {
            return new \Synchrenity\Security\SynchrenityCsrf();
        });

        $this->container->singleton('session', function ($container) {
            return new \Synchrenity\Security\SynchrenitySession();
        });
    }

    public function provides(): array
    {
        return ['csrf', 'session'];
    }
}

/**
 * HTTP Service Provider
 */
class SynchrenityHttpServiceProvider extends SynchrenityServiceProvider
{
    public function register(): void
    {
        $this->container->singleton('request', function ($container) {
            return new \Synchrenity\Http\SynchrenityRequest();
        });

        $this->container->bind('middleware', function ($container) {
            return new \Synchrenity\Support\SynchrenityMiddlewareManager();
        });
    }

    public function provides(): array
    {
        return ['request', 'middleware'];
    }
}