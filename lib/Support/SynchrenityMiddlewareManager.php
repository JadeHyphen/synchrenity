<?php
namespace Synchrenity\Support;

/**
 * SynchrenityMiddlewareManager: Fast, secure, and extensible middleware system
 * Features: global/route/event middleware, chaining, priorities, conditional logic, security hooks, dynamic loading, integration with event system.
 */
class SynchrenityMiddlewareManager
{
    protected $global = [];
    protected $route = [];
    protected $event = [];
    protected $priority = [];
    protected $securityHooks = [];

    /**
     * Register global middleware
     */
    public function registerGlobal(callable $middleware, $priority = 10)
    {
        $this->global[] = ['middleware' => $middleware, 'priority' => $priority];
        usort($this->global, fn($a, $b) => $a['priority'] <=> $b['priority']);
    }

    /**
     * Register route-specific middleware
     */
    public function registerRoute($route, callable $middleware, $priority = 10)
    {
        if (!isset($this->route[$route])) $this->route[$route] = [];
        $this->route[$route][] = ['middleware' => $middleware, 'priority' => $priority];
        usort($this->route[$route], fn($a, $b) => $a['priority'] <=> $b['priority']);
    }

    /**
     * Register event-specific middleware
     */
    public function registerEvent($event, callable $middleware, $priority = 10)
    {
        if (!isset($this->event[$event])) $this->event[$event] = [];
        $this->event[$event][] = ['middleware' => $middleware, 'priority' => $priority];
        usort($this->event[$event], fn($a, $b) => $a['priority'] <=> $b['priority']);
    }

    /**
     * Register security hook (CSRF, XSS, input validation, etc.)
     */
    public function registerSecurityHook(callable $hook)
    {
        $this->securityHooks[] = $hook;
    }

    /**
     * Execute middleware chain for a request/route/event
     */
    public function handle($type, $key, $payload = [], $context = [])
    {
        $chain = [];
        if ($type === 'global') $chain = $this->global;
        elseif ($type === 'route' && isset($this->route[$key])) $chain = $this->route[$key];
        elseif ($type === 'event' && isset($this->event[$key])) $chain = $this->event[$key];
        // Security hooks always run first
        foreach ($this->securityHooks as $hook) {
            if ($hook($payload, $context) === false) return false;
        }
        foreach ($chain as $mw) {
            if ($mw['middleware']($payload, $context) === false) return false;
        }
        return true;
    }

    /**
     * Remove middleware by reference
     */
    public function remove($type, $key, callable $middleware)
    {
        $list = &$this->{$type}[$key];
        foreach ($list as $i => $mw) {
            if ($mw['middleware'] === $middleware) unset($list[$i]);
        }
    }

    /**
     * Dynamically load middleware from config
     */
    public function autoload($config)
    {
        foreach ($config as $type => $entries) {
            foreach ($entries as $key => $specs) {
                foreach ($specs as $spec) {
                    if (is_array($spec) && count($spec) === 2) {
                        [$class, $method] = $spec;
                        if (class_exists($class) && method_exists($class, $method)) {
                            $this->register($type, $key, [new $class, $method]);
                        }
                    } elseif (is_callable($spec)) {
                        $this->register($type, $key, $spec);
                    }
                }
            }
        }
    }

    /**
     * Register middleware (helper for autoload)
     */
    protected function register($type, $key, callable $middleware)
    {
        if ($type === 'global') $this->registerGlobal($middleware);
        elseif ($type === 'route') $this->registerRoute($key, $middleware);
        elseif ($type === 'event') $this->registerEvent($key, $middleware);
    }

    /**
     * Integrate with SynchrenityEventDispatcher
     * Automatically applies event middleware when events are dispatched.
     */
    public function attachToDispatcher($dispatcher)
    {
        if (!method_exists($dispatcher, 'use')) return;
        // Attach all event middleware to dispatcher
        foreach ($this->event as $event => $middlewares) {
            foreach ($middlewares as $mw) {
                $dispatcher->use(function($e, $payload, $context) use ($event, $mw) {
                    if ($e === $event) {
                        return $mw['middleware']($payload, $context);
                    }
                    return true;
                });
            }
        }
        // Attach global middleware
        foreach ($this->global as $mw) {
            $dispatcher->use(function($e, $payload, $context) use ($mw) {
                return $mw['middleware']($payload, $context);
            });
        }
    }

    /**
     * Async middleware support (run async tasks, e.g. logging, notifications)
     */
    public function registerAsync(callable $middleware, $type = 'global', $key = null, $priority = 10)
    {
        $wrapper = function($payload, $context) use ($middleware) {
            // Run async (fire-and-forget)
            if (function_exists('pcntl_fork')) {
                $pid = pcntl_fork();
                if ($pid === 0) {
                    $middleware($payload, $context);
                    exit(0);
                }
                return true;
            } else {
                // Fallback: run synchronously
                return $middleware($payload, $context);
            }
        };
        if ($type === 'global') $this->registerGlobal($wrapper, $priority);
        elseif ($type === 'route' && $key) $this->registerRoute($key, $wrapper, $priority);
        elseif ($type === 'event' && $key) $this->registerEvent($key, $wrapper, $priority);
    }

    /**
     * Error handling/reporting for middleware chain
     */
    public function handleWithErrors($type, $key, $payload = [], $context = [], &$errors = [])
    {
        $chain = [];
        if ($type === 'global') $chain = $this->global;
        elseif ($type === 'route' && isset($this->route[$key])) $chain = $this->route[$key];
        elseif ($type === 'event' && isset($this->event[$key])) $chain = $this->event[$key];
        foreach ($this->securityHooks as $hook) {
            try {
                if ($hook($payload, $context) === false) return false;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
                return false;
            }
        }
        foreach ($chain as $mw) {
            try {
                if ($mw['middleware']($payload, $context) === false) return false;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
                return false;
            }
        }
        return true;
    }

    /**
     * Context isolation for middleware (clone context for each middleware)
     */
    public function handleIsolated($type, $key, $payload = [], $context = [])
    {
        $chain = [];
        if ($type === 'global') $chain = $this->global;
        elseif ($type === 'route' && isset($this->route[$key])) $chain = $this->route[$key];
        elseif ($type === 'event' && isset($this->event[$key])) $chain = $this->event[$key];
        foreach ($this->securityHooks as $hook) {
            $hook($payload, clone $context);
        }
        foreach ($chain as $mw) {
            $mw['middleware']($payload, clone $context);
        }
        return true;
    }

    /**
     * Before/after hooks for middleware chain
     */
    protected $beforeHooks = [];
    protected $afterHooks = [];
    public function registerBeforeHook(callable $hook) { $this->beforeHooks[] = $hook; }
    public function registerAfterHook(callable $hook) { $this->afterHooks[] = $hook; }
    public function handleWithHooks($type, $key, $payload = [], $context = [])
    {
        foreach ($this->beforeHooks as $hook) $hook($payload, $context);
        $result = $this->handle($type, $key, $payload, $context);
        foreach ($this->afterHooks as $hook) $hook($payload, $context);
        return $result;
    }

    /**
     * Conditional middleware (run only if condition is met)
     */
    public function registerConditional(callable $middleware, callable $condition, $type = 'global', $key = null, $priority = 10)
    {
        $wrapper = function($payload, $context) use ($middleware, $condition) {
            if ($condition($payload, $context)) {
                return $middleware($payload, $context);
            }
            return true;
        };
        if ($type === 'global') $this->registerGlobal($wrapper, $priority);
        elseif ($type === 'route' && $key) $this->registerRoute($key, $wrapper, $priority);
        elseif ($type === 'event' && $key) $this->registerEvent($key, $wrapper, $priority);
    }
}
