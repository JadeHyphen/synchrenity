<?php

declare(strict_types=1);

namespace Synchrenity\Http;

/**
 * SynchrenityEventManager: Register and dispatch HTTP lifecycle events
 */
class SynchrenityEventManager
{
    protected $listeners     = [];
    protected $onceListeners = [];
    protected $plugins       = [];
    protected $metrics       = [
        'events'    => 0,
        'calls'     => 0,
        'listeners' => 0,
    ];
    protected $context = [];

    // Register listener (optionally with priority)
    public function on($event, $callback, $priority = 0)
    {
        $this->listeners[$event][$priority][] = $callback;
        $this->metrics['listeners']++;
    }

    // Register one-time listener
    public function once($event, $callback, $priority = 0)
    {
        $this->onceListeners[$event][$priority][] = $callback;
        $this->metrics['listeners']++;
    }

    // Remove listener
    public function off($event, $callback = null)
    {
        if ($callback === null) {
            unset($this->listeners[$event]);
            unset($this->onceListeners[$event]);
        } else {
            foreach ([$this->listeners, $this->onceListeners] as &$group) {
                if (isset($group[$event])) {
                    foreach ($group[$event] as $priority => $cbs) {
                        $group[$event][$priority] = array_filter($cbs, function ($cb) use ($callback) {
                            return $cb !== $callback;
                        });
                    }
                }
            }
        }
    }

    // Dispatch event (supports priorities, once, wildcards, plugins, metrics)
    public function dispatch($event, ...$args)
    {
        $this->metrics['events']++;
        $called    = 0;
        $listeners = $this->getListenersForEvent($event);

        foreach ($listeners as $cb) {
            call_user_func_array($cb, $args);
            $called++;
        }

        // Once listeners: remove after call
        if (isset($this->onceListeners[$event])) {
            foreach ($this->onceListeners[$event] as $priority => $cbs) {
                foreach ($cbs as $cb) {
                    call_user_func_array($cb, $args);
                    $called++;
                }
            }
            unset($this->onceListeners[$event]);
        }

        // Wildcard listeners
        if (isset($this->listeners['*'])) {
            foreach ($this->listeners['*'] as $prio => $cbs) {
                foreach ($cbs as $cb) {
                    call_user_func_array($cb, array_merge([$event], $args));
                    $called++;
                }
            }
        }

        // Plugin hooks
        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'onEvent'])) {
                $plugin->onEvent($event, $args, $this);
            }
        }
        $this->metrics['calls'] += $called;

        return $called;
    }

    // Get listeners for event, sorted by priority
    protected function getListenersForEvent($event)
    {
        $listeners = [];

        if (isset($this->listeners[$event])) {
            krsort($this->listeners[$event]);

            foreach ($this->listeners[$event] as $prio => $cbs) {
                foreach ($cbs as $cb) {
                    $listeners[] = $cb;
                }
            }
        }

        return $listeners;
    }

    // Plugin system
    public function registerPlugin($plugin)
    {
        $this->plugins[] = $plugin;
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
    public function getListeners($event = null)
    {
        if ($event) {
            return $this->listeners[$event] ?? [];
        }

        return $this->listeners;
    }
    public function getOnceListeners($event = null)
    {
        if ($event) {
            return $this->onceListeners[$event] ?? [];
        }

        return $this->onceListeners;
    }
}
