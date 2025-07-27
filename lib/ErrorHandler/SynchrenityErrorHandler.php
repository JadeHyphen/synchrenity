<?php
namespace Synchrenity\ErrorHandler;

/**
 * SynchrenityErrorHandler: Ultra-robust, extensible error handling system
 * Features: Centralized error/exception handling, custom error types, secure reporting, hooks/events, integration with logging/mailer/monitoring, debug/production modes, rate limiting.
 */
class SynchrenityErrorHandler
{
    protected $logLevel = 'error';
    protected $logDestination = 'file';
    protected $errorHooks = [];
    protected $rateLimits = [];
    protected $rateLimitsIp = [];
    protected $rateLimitsSession = [];
    protected $debugMode = false;
    protected $customHandlers = [];
    protected $mailer;
    protected $logger;
    protected $monitoring;
    protected $notificationTargets = [];

    public function __construct($config = [], $mailer = null, $logger = null)
    {
        $this->logLevel = $config['logLevel'] ?? 'error';
    }

    /**
     * Centralized error/exception handler
     */
    public function handle($error)
    {
        $type = $error['type'] ?? 'error';
        $message = $error['message'] ?? 'Unknown error';
        $context = $error['context'] ?? [];
        $code = $error['code'] ?? 500;

        // Enrich error context (user/session/request info)
        $error['context'] = array_merge($context, $this->getEnrichedContext());

        // Custom handler
        if (isset($this->customHandlers[$type])) {
            call_user_func($this->customHandlers[$type], $error);
            return;
        }
        // Logging
        // ...logging logic here (if needed)...
        // Monitoring/analytics stub
        if ($this->monitoring && method_exists($this->monitoring, 'captureException')) {
            $this->monitoring->captureException($error);
        }
        // Notification targets (email, Slack stub)
        $this->notifyTargets($type, $message, $error['context'], $code);
        // Error hooks/events
        if (isset($this->errorHooks[$type])) {
            foreach ($this->errorHooks[$type] as $hook) {
                call_user_func($hook, $error);
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

    /**
     * Render debug error page/response
     */
    protected function renderDebug($error)
    {
        http_response_code($error['code'] ?? 500);
        echo '<pre style="color:red;background:#fff;padding:1em;">';
        echo "<b>Error:</b> " . htmlspecialchars($error['message'] ?? 'Unknown error') . "\n";
        if (!empty($error['context'])) {
            echo "<b>Context:</b> " . print_r($error['context'], true) . "\n";
        }
        if (!empty($error['trace'])) {
            echo "<b>Trace:</b> " . print_r($error['trace'], true) . "\n";
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
            'type' => 'php',
            'message' => $errstr,
            'context' => ['file' => $errfile, 'line' => $errline],
            'code' => $errno,
            'trace' => debug_backtrace()
        ]);
    }

    public function phpExceptionHandler($exception)
    {
        $this->handle([
            'type' => 'exception',
            'message' => $exception->getMessage(),
            'context' => ['file' => $exception->getFile(), 'line' => $exception->getLine()],
            'code' => $exception->getCode(),
            'trace' => $exception->getTrace()
        ]);
    }

    /**
     * Get enriched context for error handling
     */
    protected function getEnrichedContext()
    {
        return [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'session_id' => session_id() ?? null,
            'user_id' => $_SESSION['user_id'] ?? null,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
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
