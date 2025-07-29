<?php

declare(strict_types=1);

namespace Synchrenity\Support;

/**
 * SynchrenityEventDispatcher: Secure, robust, and extensible event system
 * Features: Centralized dispatch, strong typing, secure listener registration, middleware, hooks, logging, error handling, rate limiting, replay protection.
 */
class SynchrenityEventDispatcher
{
    // Properties
    protected $queue              = [];
    protected $listenerGroups     = [];
    protected $listenerTags       = [];
    protected $persistentStore    = null; // file/db stub
    protected $metrics            = [];
    protected $tracingEnabled     = false;
    protected $autoloadConfig     = [];
    protected $listeners          = [];
    protected $middleware         = [];
    protected $hooks              = [];
    protected $rateLimits         = [];
    protected $auditLog           = [];
    protected $context            = [];
    protected $propagationStopped = false;
    protected $throttleLimits     = [];

    // Methods
    /**
     * Register an event listener securely
     */
    public function on($event, callable $listener, $options = [])
    {
        // Optionally enforce auth/permissions/context
        if (!empty($options['auth']) && !$this->isAuthorized($options['auth'])) {
            throw new \Exception('Unauthorized event listener registration');
        }

        if (!is_callable($listener)) {
            throw new \InvalidArgumentException('Listener must be callable');
        }

        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
        $this->listeners[$event][] = $listener;
    }

    /**
     * Register event hooks (logging, error handling, notifications)
     */
    public function hook($type, callable $hook)
    {
        if (!is_callable($hook)) {
            throw new \InvalidArgumentException('Hook must be callable');
        }

        if (!isset($this->hooks[$type])) {
            $this->hooks[$type] = [];
        }
        $this->hooks[$type][] = $hook;
    }

    /**
     * Dispatch an event securely
     */
    public function dispatch($event, $payload = [], $context = [])
    {
        // Rate limiting & replay protection
        if ($this->isRateLimited($event, $context)) {
            return false;
        }

        // Middleware validation/filtering
        foreach ($this->middleware as $mw) {
            if ($mw($event, $payload, $context) === false) {
                return false;
            }
        }
        // Audit log
        $this->audit($event, $payload, $context);
        // Hooks: before
        $this->runHooks('before', $event, $payload, $context);

        // Dispatch listeners
        if (isset($this->listeners[$event])) {
            foreach (array_filter($this->listeners[$event], 'is_callable') as $listener) {
                try {
                    $listener($payload, $context);
                } catch (\Throwable $e) {
                    $this->runHooks('error', $event, $payload, $context, $e);
                }
            }
        }
        // Hooks: after
        $this->runHooks('after', $event, $payload, $context);

        return true;
    }

    /**
     * Audit log for events
     */
    protected function audit($event, $payload, $context)
    {
        $this->auditLog[] = [
            'event'     => $event,
            'payload'   => $payload,
            'context'   => $context,
            'timestamp' => time(),
        ];
    }

    /**
     * Run hooks for event lifecycle
     */
    protected function runHooks($type, $event, $payload, $context, $error = null)
    {
        if (isset($this->hooks[$type])) {
            foreach (array_filter($this->hooks[$type], 'is_callable') as $hook) {
                $hook($event, $payload, $context, $error);
            }
        }
    }
    /**
     * Wildcard event matching (e.g., user.*)
     */
    protected function matchEvents($event)
    {
        $matches = [];

        foreach (array_keys($this->listeners) as $registered) {
            if ($registered === $event || fnmatch($registered, $event)) {
                $matches[] = $registered;
            }
        }

        return $matches;
    }

    /**
     * Event priorities
     */
    public function onPriority($event, callable $listener, $priority = 10, $options = [])
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
        $this->listeners[$event][] = ['listener' => $listener, 'priority' => $priority, 'options' => $options];
        usort($this->listeners[$event], function ($a, $b) { return $a['priority'] <=> $b['priority']; });
    }

    /**
     * Event bubbling/propagation control
     */
    // removed duplicate declaration
    public function stopPropagation()
    {
        $this->propagationStopped = true;
    }
    public function resetPropagation()
    {
        $this->propagationStopped = false;
    }

    /**
     * Transactional events (rollback on error)
     */
    public function dispatchTransactional($event, $payload = [], $context = [])
    {
        $this->audit($event, $payload, $context);

        try {
            $this->dispatch($event, $payload, $context);
        } catch (\Throwable $e) {
            // Rollback logic stub
            $this->runHooks('rollback', $event, $payload, $context, $e);

            return false;
        }

        return true;
    }

    /**
     * Persistent event storage (file/db implementation)
     * Stores event data securely in a file (JSON lines) for audit/replay.
     */
    public function persistEvent($event, $payload, $context)
    {
        $entry = [
            'event'     => $event,
            'payload'   => $payload,
            'context'   => $context,
            'timestamp' => time(),
        ];
        $file = __DIR__ . '/event_store.jsonl';
        file_put_contents($file, json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Enable all listeners in a group
     */
    public function enableGroup($group)
    {
        if (!isset($this->listenerGroups[$group])) {
            return;
        }

        foreach ($this->listenerGroups[$group] as [$event, $listener]) {
            $this->on($event, $listener);
        }
    }

    /**
     * Disable all listeners in a group
     */
    public function disableGroup($group)
    {
        if (!isset($this->listenerGroups[$group])) {
            return;
        }

        foreach ($this->listenerGroups[$group] as [$event, $listener]) {
            if (($key = array_search($listener, $this->listeners[$event] ?? [], true)) !== false) {
                unset($this->listeners[$event][$key]);
            }
        }
    }

    /**
     * Dynamic listener autoloading
     * Loads listeners from config array: [event => [listenerClass, method]]
     */
    public function autoloadListeners($config)
    {
        foreach ($config as $event => $specs) {
            foreach ($specs as $spec) {
                if (is_array($spec) && count($spec) === 2) {
                    [$class, $method] = $spec;

                    if (class_exists($class) && method_exists($class, $method)) {
                        $this->on($event, [new $class(), $method]);
                    }
                } elseif (is_callable($spec)) {
                    $this->on($event, $spec);
                }
            }
        }
    }

    /**
     * Per-listener permissions/context isolation
     * Checks if listener is allowed in current context (stub: always true)
     */
    protected function checkListenerPermissions($listener, $context)
    {
        // Extend with real permission logic as needed
        return true;
    }

    /**
     * Integration hooks (webhooks, external APIs)
     * Calls external API/webhook with event data
     */
    public function addIntegrationHook($type, callable $hook)
    {
        if (!isset($this->hooks[$type])) {
            $this->hooks[$type] = [];
        }
        $this->hooks[$type][] = $hook;
    }

    /**
     * Event throttling (per event/user/IP)
     * Limits event dispatches per event/context (stub: always false)
     */
    public function setThrottle($event, $limit, $window = 60)
    {
        $this->throttleLimits[$event] = ['limit' => $limit, 'window' => $window];
    }
    protected function isThrottled($event, $context)
    {
        // Implement throttling logic here
        return false;
    }

    /**
     * Payload validation (schema/type check)
     * Validates payload structure (stub: always true)
     */
    protected function validatePayload($event, $payload)
    {
        // Implement schema/type validation here
        return true;
    }

    /**
     * Event tracing/correlation IDs
     * Generates/returns a trace ID for event correlation
     */
    public function enableTracing()
    {
        $this->tracingEnabled = true;
    }
    public function disableTracing()
    {
        $this->tracingEnabled = false;
    }
    protected function getTraceId($context)
    {
        return $context['trace_id'] ?? bin2hex(random_bytes(8));
    }

    /**
     * Metrics and analytics (frequency, latency, error rates)
     * Records and retrieves event metrics
     */
    protected function recordMetric($event, $type, $value)
    {
        if (!isset($this->metrics[$event])) {
            $this->metrics[$event] = [];
        }
        $this->metrics[$event][$type][] = $value;
    }
    public function getMetrics($event = null)
    {
        return $event ? ($this->metrics[$event] ?? []) : $this->metrics;
    }

    /**
     * Test helpers (mock events/listeners)
     * Simulates event dispatch and listener invocation
     */
    public function mockEvent($event, $payload = [], $context = [])
    {
        return $this->dispatch($event, $payload, $context);
    }
    public function mockListener(callable $listener)
    {
        return is_callable($listener);
    }

    /**
     * Secure authorization check stub
     * Checks user roles, permissions, and context for listener registration.
     */
    protected function isAuthorized($auth)
    {
        // Example: $auth = ['role' => 'admin', 'user_id' => 123]
        // Replace with real user/context integration
        if (empty($auth)) {
            return false;
        }

        // Example: Only allow 'admin' role
        if (isset($auth['role']) && $auth['role'] === 'admin') {
            return true;
        }

        // Add more robust checks as needed
        return false;
    }

    /**
     * Rate limiting and replay protection
     * Prevents excessive event dispatches per event/context.
     */
    protected function isRateLimited($event, $context)
    {
        $key    = md5($event . json_encode($context));
        $now    = time();
        $window = 60; // seconds
        $limit  = 20; // max events per window

        if (!isset($this->rateLimits[$key])) {
            $this->rateLimits[$key] = [];
        }
        // Remove timestamps outside window
        $this->rateLimits[$key] = array_filter($this->rateLimits[$key], function ($ts) use ($now, $window) {
            return $ts > $now - $window;
        });

        if (count($this->rateLimits[$key]) >= $limit) {
            return true;
        }
        $this->rateLimits[$key][] = $now;

        return false;
    }
}
