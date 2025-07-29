<?php

declare(strict_types=1);

namespace Synchrenity\Http;

/**
 * SynchrenityMiddleware: Stackable, before/after hooks, error handling, security
 */
class SynchrenityMiddleware
{
    protected $beforeHooks = [];
    protected $afterHooks  = [];
    protected $errorHooks  = [];
    protected $plugins     = [];
    protected $events      = [];
    protected $metrics     = [
        'calls'   => 0,
        'errors'  => 0,
        'success' => 0,
    ];
    protected $context = [];
    protected $enabled = true;

    public function handle($request, $next = null)
    {
        if (!$this->enabled) {
            return $next ? $next($request) : $request;
        }
        $this->metrics['calls']++;

        try {
            foreach ($this->beforeHooks as $hook) {
                $request = call_user_func($hook, $request, $this);
            }

            foreach ($this->plugins as $plugin) {
                if (is_callable([$plugin, 'before'])) {
                    $request = $plugin->before($request, $this);
                }
            }
            $response = $next ? $next($request) : $request;

            foreach ($this->afterHooks as $hook) {
                $response = call_user_func($hook, $response, $this);
            }

            foreach ($this->plugins as $plugin) {
                if (is_callable([$plugin, 'after'])) {
                    $response = $plugin->after($response, $this);
                }
            }
            $this->metrics['success']++;
            $this->triggerEvent('success', $response);

            return $response;
        } catch (\Throwable $e) {
            $this->metrics['errors']++;

            foreach ($this->errorHooks as $hook) {
                $result = call_user_func($hook, $e, $request, $this);

                if ($result !== null) {
                    return $result;
                }
            }

            foreach ($this->plugins as $plugin) {
                if (is_callable([$plugin, 'error'])) {
                    $result = $plugin->error($e, $request, $this);

                    if ($result !== null) {
                        return $result;
                    }
                }
            }
            $this->triggerEvent('error', $e);

            throw $e;
        }
    }

    // Async/await stub (for future async PHP support)
    public function handleAsync($request, $next = null)
    {
        // In real async PHP, this would be an async coroutine
        return $this->handle($request, $next);
    }

    // Middleware chaining/composition
    public function chain($middleware)
    {
        $prev = $this;

        return new class ($prev, $middleware) extends SynchrenityMiddleware {
            private $prev;
            private $nextMw;
            public function __construct($prev, $nextMw)
            {
                $this->prev   = $prev;
                $this->nextMw = $nextMw;
            }
            public function handle($request, $next = null)
            {
                return $this->prev->handle($request, function ($req) {
                    return $this->nextMw->handle($req);
                });
            }
        };
    }

    // Enable/disable
    public function enable()
    {
        $this->enabled = true;
    }
    public function disable()
    {
        $this->enabled = false;
    }

    // Advanced context
    public function setContextArray(array $ctx)
    {
        $this->context = $ctx;
    }
    public function getContextAll()
    {
        return $this->context;
    }

    // Hook registration
    public function before(callable $cb)
    {
        $this->beforeHooks[] = $cb;
    }
    public function after(callable $cb)
    {
        $this->afterHooks[] = $cb;
    }
    public function onError(callable $cb)
    {
        $this->errorHooks[] = $cb;
    }

    // Plugin system
    public function registerPlugin($plugin)
    {
        $this->plugins[] = $plugin;
    }

    // Event system
    public function on($event, callable $cb)
    {
        $this->events[$event][] = $cb;
    }
    protected function triggerEvent($event, $data = null)
    {
        foreach ($this->events[$event] ?? [] as $cb) {
            call_user_func($cb, $data, $this);
        }
    }

    // Context
    public function setContext($key, $value)
    {
        $this->context[$key] = $value;
    }
    public function getContext($key, $default = null)
    {
        return $this->context[$key] ?? $default;
    }

    // Metrics
    public function getMetrics()
    {
        return $this->metrics;
    }

    // Introspection
    public function getBeforeHooks()
    {
        return $this->beforeHooks;
    }
    public function getAfterHooks()
    {
        return $this->afterHooks;
    }
    public function getErrorHooks()
    {
        return $this->errorHooks;
    }
    public function getPlugins()
    {
        return $this->plugins;
    }
    public function getEvents()
    {
        return $this->events;
    }
}
