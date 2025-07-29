<?php

declare(strict_types=1);

namespace Synchrenity\ErrorHandler;

/**
 * SynchrenityErrorHandler: Ultra-robust, extensible error handling system
 * Features: Centralized error/exception handling, custom error types, secure reporting, hooks/events, integration with logging/mailer/monitoring, debug/production modes, rate limiting.
 */
class SynchrenityErrorHandler
{
    protected $logLevel          = 'error';
    protected $logDestination    = 'file';
    protected $errorHooks        = [];
    protected $rateLimits        = [];
    protected $rateLimitsIp      = [];
    protected $rateLimitsSession = [];
    protected $debugMode         = false;
    protected $customHandlers    = [];
    protected $mailer;
    protected $logger;
    protected $monitoring;
    protected $notificationTargets = [];
    protected $plugins             = [];
    protected $events              = [];
    protected $metrics             = [
        'handled'      => 0,
        'rate_limited' => 0,
        'notified'     => 0,
        'errors'       => 0,
    ];
    protected $context    = [];
    protected $errorIds   = [];
    protected $suppressed = [];

    public function __construct($config = [], $mailer = null, $logger = null)
    {
        $this->logLevel = $config['logLevel'] ?? 'error';
    }

    /**
     * Centralized error/exception handler
     */
    public function handle($error)
    {
        $this->metrics['handled']++;
        $type             = $error['type']    ?? 'error';
        $message          = $error['message'] ?? 'Unknown error';
        $context          = $error['context'] ?? [];
        $code             = $error['code']    ?? 500;
        $errorId          = $this->generateErrorId($error);
        $error['id']      = $errorId;
        $error['context'] = array_merge($context, $this->getEnrichedContext());
        $this->context    = $error['context'];

        // Rate limiting (per type, IP, session, deduplication)
        if ($this->isRateLimited($type, $errorId)) {
            $this->metrics['rate_limited']++;
            $this->triggerEvent('rate_limited', $error);

            foreach ($this->plugins as $plugin) {
                if (is_callable([$plugin, 'onRateLimited'])) {
                    $plugin->onRateLimited($error, $this);
                }
            }

            return;
        }

        // Suppression
        if ($this->isSuppressed($type, $errorId)) {
            return;
        }

        // Custom handler
        if (isset($this->customHandlers[$type])) {
            call_user_func($this->customHandlers[$type], $error);

            return;
        }

        // Logging
        if ($this->logger && method_exists($this->logger, 'log')) {
            $this->logger->log($this->logLevel, $message, $error['context']);
        }

        // Monitoring/analytics stub
        if ($this->monitoring && method_exists($this->monitoring, 'captureException')) {
            $this->monitoring->captureException($error);
        }
        // Notification targets (email, Slack stub)
        $this->notifyTargets($type, $message, $error['context'], $code);
        $this->metrics['notified']++;

        // Error hooks/events
        if (isset($this->errorHooks[$type])) {
            foreach ($this->errorHooks[$type] as $hook) {
                call_user_func($hook, $error);
            }
        }
        $this->triggerEvent('handled', $error);

        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'onHandled'])) {
                $plugin->onHandled($error, $this);
            }
        }
        // Automated recovery/fallback stub
        $this->attemptRecovery($error);

        // Secure error response
        if ($this->debugMode) {
            $this->renderDebug($error);
        } else {
            $this->renderProduction($error);
        }
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
    // Metrics
    public function getMetrics()
    {
        return $this->metrics;
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
    // Introspection
    public function getPlugins()
    {
        return $this->plugins;
    }
    public function getEvents()
    {
        return $this->events;
    }
    // Error ID generation (for deduplication, replay, suppression)
    protected function generateErrorId($error)
    {
        return hash('sha256', serialize([
            $error['type']    ?? '',
            $error['message'] ?? '',
            $error['code']    ?? '',
            $error['context'] ?? '',
        ]));
    }
    // Rate limiting logic (per type, IP, session, deduplication)
    protected function isRateLimited($type, $errorId)
    {
        $ip            = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $session       = session_id()            ?? 'none';
        $now           = time();
        $window        = 60; // seconds
        $maxPerType    = 10;
        $maxPerIp      = 20;
        $maxPerSession = 20;
        $maxPerError   = 5;
        // Per type
        $this->rateLimits[$type]   = array_filter($this->rateLimits[$type] ?? [], fn ($t) => $t > $now - $window);
        $this->rateLimits[$type][] = $now;

        if (count($this->rateLimits[$type]) > $maxPerType) {
            return true;
        }
        // Per IP
        $this->rateLimitsIp[$ip]   = array_filter($this->rateLimitsIp[$ip] ?? [], fn ($t) => $t > $now - $window);
        $this->rateLimitsIp[$ip][] = $now;

        if (count($this->rateLimitsIp[$ip]) > $maxPerIp) {
            return true;
        }
        // Per session
        $this->rateLimitsSession[$session]   = array_filter($this->rateLimitsSession[$session] ?? [], fn ($t) => $t > $now - $window);
        $this->rateLimitsSession[$session][] = $now;

        if (count($this->rateLimitsSession[$session]) > $maxPerSession) {
            return true;
        }
        // Per errorId
        $this->errorIds[$errorId]   = array_filter($this->errorIds[$errorId] ?? [], fn ($t) => $t > $now - $window);
        $this->errorIds[$errorId][] = $now;

        if (count($this->errorIds[$errorId]) > $maxPerError) {
            return true;
        }

        return false;
    }
    // Error suppression (manual or auto)
    public function suppress($type, $errorId)
    {
        $this->suppressed[$type][$errorId] = true;
    }
    protected function isSuppressed($type, $errorId)
    {
        return !empty($this->suppressed[$type][$errorId]);
    }
    // Error replay (re-handle suppressed/deduped errors)
    public function replay($error)
    {
        $this->handle($error);
    }

    /**
     * Render debug error page/response
     */
    protected function renderDebug($error)
    {
        http_response_code($error['code'] ?? 500);
        echo '<pre style="color:red;background:#fff;padding:1em;">';
        echo '<b>Error:</b> ' . htmlspecialchars($error['message'] ?? 'Unknown error') . "\n";

        if (!empty($error['context'])) {
            echo '<b>Context:</b> ' . print_r($error['context'], true) . "\n";
        }

        if (!empty($error['trace'])) {
            echo '<b>Trace:</b> ' . print_r($error['trace'], true) . "\n";
        }
        echo '</pre>';
    }

    /**
     * Render production error page/response
     */
    protected function renderProduction($error)
    {
        http_response_code($error['code'] ?? 500);
        echo '<h2>Something went wrong</h2>';
        echo '<p>Please contact support or try again later.</p>';

        // Optionally show error code or support link
        if (!empty($error['code'])) {
            echo '<p>Error Code: ' . htmlspecialchars($error['code']) . '</p>';
        }
    }

    /**
     * Register as global error/exception handler
     */
    public function register()
    {
        set_error_handler([$this, 'phpErrorHandler']);
        set_exception_handler([$this, 'phpExceptionHandler']);
    }

    public function phpErrorHandler($errno, $errstr, $errfile, $errline)
    {
        $this->handle([
            'type'    => 'php',
            'message' => $errstr,
            'context' => ['file' => $errfile, 'line' => $errline],
            'code'    => $errno,
            'trace'   => debug_backtrace(),
        ]);
    }

    public function phpExceptionHandler($exception)
    {
        $this->handle([
            'type'    => 'exception',
            'message' => $exception->getMessage(),
            'context' => ['file' => $exception->getFile(), 'line' => $exception->getLine()],
            'code'    => $exception->getCode(),
            'trace'   => $exception->getTrace(),
        ]);
    }

    /**
     * Get enriched context for error handling
     */
    protected function getEnrichedContext()
    {
        return [
            'ip'          => $_SERVER['REMOTE_ADDR']     ?? null,
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'session_id'  => session_id()                ?? null,
            'user_id'     => $_SESSION['user_id']        ?? null,
            'request_uri' => $_SERVER['REQUEST_URI']     ?? null,
        ];
    }

    /**
     * Notify all configured targets (email, Slack stub)
     */
    protected function notifyTargets($type, $message, $context, $code)
    {
        foreach ($this->notificationTargets as $target) {
            if (filter_var($target, FILTER_VALIDATE_EMAIL) && $this->mailer) {
                $this->mailer->send($target, 'Error Notification', "[$type] $message | Code: $code | Context: " . json_encode($context));
            } elseif (strpos($target, 'https://hooks.slack.com/') === 0) {
                // Slack webhook stub
                // You can implement real Slack notification here
            }
        }
    }

    /**
     * Attempt automated recovery/fallback (stub)
     */
    protected function attemptRecovery($error)
    {
        // Implement custom recovery logic here, e.g. restart service, clear cache, etc.
    }
}


// ...existing code...
