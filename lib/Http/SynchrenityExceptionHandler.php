<?php
namespace Synchrenity\Http;

/**
 * SynchrenityExceptionHandler: Centralized, customizable, logs, friendly error pages
 */
class SynchrenityExceptionHandler
{
    protected $debug = false;
    protected $customView = null;

    public function __construct($debug = false, $customView = null)
    {
        $this->debug = $debug;
        $this->customView = $customView;
    }

    public function handle($e)
    {
        error_log('[Synchrenity Exception] ' . $e->getMessage());
        http_response_code(500);
        // Event hook for error reporting
        if (class_exists('Synchrenity\\Http\\SynchrenityEventManager')) {
            static $eventManager;
            if (!$eventManager) $eventManager = new \Synchrenity\Http\SynchrenityEventManager();
            $eventManager->dispatch('error', $e);
        }
        // Sentry/Bugsnag integration stub
        if (defined('SYNCHRENITY_SENTRY_DSN')) {
            // send error to Sentry
        }
        if (defined('SYNCHRENITY_BUGSNAG_APIKEY')) {
            // send error to Bugsnag
        }
        if ($this->customView && is_callable($this->customView)) {
            call_user_func($this->customView, $e);
            return;
        }
        if ($this->debug) {
            echo "<h1>Internal Server Error</h1>";
            echo "<pre>" . htmlspecialchars($e) . "</pre>";
        } else {
            echo "<h1>Internal Server Error</h1>";
            echo "<p>An unexpected error occurred. Please try again later.</p>";
        }
    }
}
