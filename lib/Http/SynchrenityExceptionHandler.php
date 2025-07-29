<?php

declare(strict_types=1);

namespace Synchrenity\Http;

/**
 * SynchrenityExceptionHandler: Centralized, customizable, logs, friendly error pages
 */
class SynchrenityExceptionHandler
{
    protected $debug      = false;
    protected $customView = null;
    protected $plugins    = [];
    protected $events     = [];
    protected $metrics    = [
        'calls'    => 0,
        'reported' => 0,
        'custom'   => 0,
        'external' => 0,
    ];
    protected $context = [];

    public function __construct($debug = false, $customView = null)
    {
        $this->debug      = $debug;
        $this->customView = $customView;
    }

    public function handle($e)
    {
        $this->metrics['calls']++;
        $this->logError($e);
        http_response_code(500);
        $this->triggerEvent('before', $e);

        // Plugin hooks
        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'onException'])) {
                $plugin->onException($e, $this);
            }
        }

        // Event hook for error reporting
        if (class_exists('Synchrenity\\Http\\SynchrenityEventManager')) {
            static $eventManager;

            if (!$eventManager) {
                $eventManager = new \Synchrenity\Http\SynchrenityEventManager();
            }
            $eventManager->dispatch('error', $e);
        }

        // Sentry/Bugsnag integration stub
        if (defined('SYNCHRENITY_SENTRY_DSN')) {
            $this->metrics['external']++;
            $this->reportToSentry($e);
        }

        if (defined('SYNCHRENITY_BUGSNAG_APIKEY')) {
            $this->metrics['external']++;
            $this->reportToBugsnag($e);
        }

        // Custom view
        if ($this->customView && is_callable($this->customView)) {
            $this->metrics['custom']++;
            call_user_func($this->customView, $e, $this);
            $this->triggerEvent('custom', $e);

            return;
        }

        // Advanced debug output
        if ($this->debug) {
            echo '<h1>Internal Server Error</h1>';
            echo '<pre>' . htmlspecialchars($this->formatException($e)) . '</pre>';
        } else {
            echo '<h1>Internal Server Error</h1>';
            echo '<p>An unexpected error occurred. Please try again later.</p>';
        }
        $this->triggerEvent('after', $e);
    }

    // Logging
    protected function logError($e)
    {
        error_log('[Synchrenity Exception] ' . $e->getMessage());

        if (method_exists($e, 'getTraceAsString')) {
            error_log($e->getTraceAsString());
        }
    }

    // Sentry/Bugsnag stubs
    protected function reportToSentry($e)
    {
        // Integrate with Sentry SDK if available
    }
    protected function reportToBugsnag($e)
    {
        // Integrate with Bugsnag SDK if available
    }

    // Exception formatting
    protected function formatException($e)
    {
        if ($e instanceof \Throwable) {
            return $e->getMessage() . "\n" . $e->getTraceAsString();
        }

        return print_r($e, true);
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
}
