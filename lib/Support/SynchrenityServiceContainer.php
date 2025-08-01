<?php

declare(strict_types=1);

namespace Synchrenity\Support;

use Psr\Container\ContainerInterface;

/**
 * Enhanced Synchrenity Service Container
 * PSR-11 compliant with advanced features for dependency injection
 */
class SynchrenityServiceContainer implements ContainerInterface
{
    protected array $bindings = [];
    protected array $instances = [];
    protected array $singletons = [];
    protected array $providers = [];
    protected array $aliases = [];
    protected array $contextual = [];
    protected array $config = [];
    protected array $deferred = [];
    protected array $tags = [];
    protected array $resolving = [];
    protected bool $booted = false;

    /**
     * Register a service binding
     */
    public function bind(string $name, callable $factory): void
    {
        $this->bindings[$name] = $factory;
    }

    /**
     * Register a service as singleton
     */
    public function singleton(string $name, callable $factory): void
    {
        $this->singletons[$name] = $factory;
    }

    /**
     * Register an existing instance
     */
    public function instance(string $name, $object): void
    {
        $this->instances[$name] = $object;
    }

    /**
     * Register an alias for a service
     */
    public function alias(string $alias, string $name): void
    {
        $this->aliases[$alias] = $name;
    }

    /**
     * Register contextual binding
     */
    public function when(string $context, string $name, callable $factory): void
    {
        $this->contextual[$context][$name] = $factory;
    }

    /**
     * Register deferred/lazy service
     */
    public function defer(string $name, callable $factory): void
    {
        $this->deferred[$name] = $factory;
    }

    /**
     * Tag services for group resolution
     */
    public function tag(string $name, array $tags): void
    {
        foreach ($tags as $tag) {
            $this->tags[$tag][] = $name;
        }
    }

    /**
     * Get services by tag
     */
    public function tagged(string $tag): array
    {
        $services = [];
        foreach ($this->tags[$tag] ?? [] as $serviceName) {
            $services[] = $this->get($serviceName);
        }
        return $services;
    }

    /**
     * PSR-11: Check if service exists
     */
    public function has(string $id): bool
    {
        // Check aliases first
        $name = $this->aliases[$id] ?? $id;

        return isset($this->bindings[$name]) || 
               isset($this->singletons[$name]) || 
               isset($this->instances[$name]) || 
               isset($this->deferred[$name]) ||
               isset($this->config[$name]) ||
               class_exists($name);
    }

    /**
     * PSR-11: Get service
     */
    public function get(string $id)
    {
        // Prevent circular dependencies
        if (isset($this->resolving[$id])) {
            throw new \RuntimeException("Circular dependency detected for service: {$id}");
        }

        $this->resolving[$id] = true;

        try {
            $service = $this->resolve($id);
            unset($this->resolving[$id]);
            return $service;
        } catch (\Throwable $e) {
            unset($this->resolving[$id]);
            throw $e;
        }
    }

    /**
     * Resolve service
     */
    protected function resolve(string $id)
    {
        // Check aliases
        $name = $this->aliases[$id] ?? $id;

        // Check for existing instances
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        // Check contextual bindings
        $context = $this->getCallingContext();
        if ($context && isset($this->contextual[$context][$name])) {
            return call_user_func($this->contextual[$context][$name], $this);
        }

        // Check singletons
        if (isset($this->singletons[$name])) {
            $this->instances[$name] = call_user_func($this->singletons[$name], $this);
            return $this->instances[$name];
        }

        // Check regular bindings
        if (isset($this->bindings[$name])) {
            return call_user_func($this->bindings[$name], $this);
        }

        // Check deferred services
        if (isset($this->deferred[$name])) {
            $this->bindings[$name] = $this->deferred[$name];
            unset($this->deferred[$name]);
            return call_user_func($this->bindings[$name], $this);
        }

        // Check config
        if (isset($this->config[$name])) {
            return $this->config[$name];
        }

        // Try auto-wiring for classes
        if (class_exists($name)) {
            return $this->make($name);
        }

        throw new \RuntimeException("Service '{$name}' not found in container");
    }

    /**
     * Auto-wire class using reflection
     */
    public function make(string $class)
    {
        if (!class_exists($class)) {
            throw new \RuntimeException("Class '{$class}' does not exist");
        }

        $reflector = new \ReflectionClass($class);
        
        if (!$reflector->isInstantiable()) {
            throw new \RuntimeException("Class '{$class}' is not instantiable");
        }

        $constructor = $reflector->getConstructor();

        if (!$constructor) {
            return new $class();
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependencies[] = $this->resolveParameter($parameter);
        }

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve constructor parameter
     */
    protected function resolveParameter(\ReflectionParameter $parameter)
    {
        $type = $parameter->getType();

        if ($type && !$type->isBuiltin()) {
            $typeName = $type->getName();
            
            // Try to resolve from container first
            if ($this->has($typeName)) {
                return $this->get($typeName);
            }
            
            // Try auto-wiring
            if (class_exists($typeName)) {
                return $this->make($typeName);
            }
        }

        // Use default value if available
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        // Allow null if nullable
        if ($parameter->allowsNull()) {
            return null;
        }

        throw new \RuntimeException(
            "Cannot resolve parameter '{$parameter->getName()}' for class construction"
        );
    }

    /**
     * Get calling context for contextual bindings
     */
    protected function getCallingContext(): ?string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        
        foreach ($trace as $frame) {
            if (isset($frame['class']) && $frame['class'] !== self::class) {
                return $frame['class'];
            }
        }
        
        return null;
    }

    /**
     * Register a service provider
     */
    public function registerProvider($provider): void
    {
        $this->providers[] = $provider;
        
        if (method_exists($provider, 'register')) {
            $provider->register($this);
        }
    }

    /**
     * Boot all registered providers
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->providers as $provider) {
            if (method_exists($provider, 'boot')) {
                $provider->boot($this);
            }
        }

        $this->booted = true;
    }

    /**
     * Load configuration into container
     */
    public function loadConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Call method with dependency injection
     */
    public function call(callable $callback, array $parameters = [])
    {
        if (is_array($callback)) {
            $reflection = new \ReflectionMethod($callback[0], $callback[1]);
        } elseif (is_object($callback) && method_exists($callback, '__invoke')) {
            $reflection = new \ReflectionMethod($callback, '__invoke');
        } else {
            $reflection = new \ReflectionFunction($callback);
        }

        $dependencies = [];
        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();
            
            if (array_key_exists($name, $parameters)) {
                $dependencies[] = $parameters[$name];
            } else {
                $dependencies[] = $this->resolveParameter($parameter);
            }
        }

        return call_user_func_array($callback, $dependencies);
    }

    /**
     * Flush all services (useful for testing)
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->singletons = [];
        $this->aliases = [];
        $this->contextual = [];
        $this->deferred = [];
        $this->tags = [];
        $this->resolving = [];
        $this->booted = false;
    }

    /**
     * Get all registered service names
     */
    public function getRegisteredServices(): array
    {
        return array_unique(array_merge(
            array_keys($this->bindings),
            array_keys($this->singletons),
            array_keys($this->instances),
            array_keys($this->deferred),
            array_keys($this->config)
        ));
    }

    /**
     * Check if container is booted
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }
}

// Global container instance
$GLOBALS['synchrenityContainer'] = $GLOBALS['synchrenityContainer'] ?? new SynchrenityServiceContainer();

/**
 * Global helper functions for easier access
 */
function app(?string $service = null)
{
    $container = $GLOBALS['synchrenityContainer'];
    
    if ($service === null) {
        return $container;
    }
    
    return $container->get($service);
}

function auth()
{
    return app('auth');
}

function cache()
{
    return app('cache');
}

function config(string $key = null, $default = null)
{
    $configService = app('config');
    
    if ($key === null) {
        return $configService;
    }
    
    return $configService[$key] ?? $default;
}

function logger()
{
    return app('logger');
}

function synchrenity(string $service)
{
    return app($service);
}

function container(): SynchrenityServiceContainer
{
    return $GLOBALS['synchrenityContainer'];
}
