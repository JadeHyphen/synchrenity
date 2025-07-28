<?php
namespace Synchrenity\Support;


class SynchrenityServiceContainer {
    protected $aliases = [];
    protected $contextual = [];
    protected $config = [];
    protected $deferred = [];

    // Service aliasing
    public function alias($alias, $name) {
        $this->aliases[$alias] = $name;
    }

    // Contextual binding (per class/request)
    public function when($context, $name, callable $factory) {
        $this->contextual[$context][$name] = $factory;
    }

    // Deferred/lazy loading
    public function defer($name, callable $factory) {
        $this->deferred[$name] = $factory;
    }

    // Configuration-based service loading
    public function loadConfig(array $config) {
        $this->config = $config;
    }

    // Auto-wiring via reflection (constructor injection)
    public function make($class) {
        $reflector = new \ReflectionClass($class);
        $constructor = $reflector->getConstructor();
        if (!$constructor) {
            return new $class;
        }
        $params = $constructor->getParameters();
        $dependencies = [];
        foreach ($params as $param) {
            $type = $param->getType();
            if ($type && !$type->isBuiltin()) {
                $depClass = $type->getName();
                $dependencies[] = $this->make($depClass);
            } else {
                $dependencies[] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
            }
        }
        return $reflector->newInstanceArgs($dependencies);
    }
    protected $bindings = [];
    protected $instances = [];
    protected $singletons = [];
    protected $providers = [];

    public function register($name, callable $factory) {
        $this->bindings[$name] = $factory;
    }
    public function bind($name, callable $factory) {
        $this->bindings[$name] = $factory;
    }
    public function singleton($name, callable $factory) {
        $this->singletons[$name] = $factory;
    }
    public function instance($name, $object) {
        $this->instances[$name] = $object;
    }
    public function get($name) {
        // Aliases
        if (isset($this->aliases[$name])) {
            $name = $this->aliases[$name];
        }
        // Contextual
        $context = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['class'] ?? null;
        if ($context && isset($this->contextual[$context][$name])) {
            return call_user_func($this->contextual[$context][$name], $this);
        }
        // Deferred
        if (isset($this->deferred[$name])) {
            $this->bindings[$name] = $this->deferred[$name];
            unset($this->deferred[$name]);
        }
        // Config
        if (isset($this->config[$name])) {
            return $this->config[$name];
        }
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }
        if (isset($this->singletons[$name])) {
            $this->instances[$name] = call_user_func($this->singletons[$name], $this);
            return $this->instances[$name];
        }
        if (isset($this->bindings[$name])) {
            return call_user_func($this->bindings[$name], $this);
        }
        throw new \Exception("Service '$name' not registered.");
    }
    public function has($name) {
        if (isset($this->aliases[$name])) {
            $name = $this->aliases[$name];
        }
        return isset($this->bindings[$name]) || isset($this->singletons[$name]) || isset($this->instances[$name]) || isset($this->contextual[$name]) || isset($this->deferred[$name]) || isset($this->config[$name]);
    }
    public function registerProvider($provider) {
        $this->providers[] = $provider;
        $provider->register($this);
    }
}

// Global helpers
function auth() {
    global $synchrenityContainer;
    return $synchrenityContainer->get('auth');
}
function atlas() {
    global $synchrenityContainer;
    return $synchrenityContainer->get('atlas');
}
function synchrenity($name) {
    global $synchrenityContainer;
    return $synchrenityContainer->get($name);
}

// Global helper

