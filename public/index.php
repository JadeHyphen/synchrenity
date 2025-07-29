<?php
// public/index.php

// Synchrenity Public Entry Point
// This file is the main entry for all HTTP requests. It loads the framework and handles requests.

// --- Security Headers ---
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\';');
header('X-XSS-Protection: 1; mode=block');

// --- Error Reporting ---
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// --- Request Logging (basic) ---
if (!empty($_SERVER['REQUEST_METHOD'])) {
    error_log('[Synchrenity] ' . $_SERVER['REQUEST_METHOD'] . ' ' . ($_SERVER['REQUEST_URI'] ?? '') . ' from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

// --- Synchrenity Core System Initialization ---
// Use Composer autoloading
require_once __DIR__ . '/../vendor/autoload.php';

// Robust config/env validation
if (!file_exists(__DIR__ . '/../.env')) {
    error_log('[Synchrenity Error] .env file missing.');
    http_response_code(500);
    echo "<h1>Configuration error: .env file missing</h1>";
    exit(1);
}
if (!file_exists(__DIR__ . '/../config/app.php')) {
    error_log('[Synchrenity Error] config/app.php missing.');
    http_response_code(500);
    echo "<h1>Configuration error: config/app.php missing</h1>";
    exit(1);
}

// Global exception handler
set_exception_handler(function($e) {
    error_log('[Synchrenity Uncaught Exception] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo "<h1>Internal Server Error</h1>";
    exit(1);
});

// Initialize core systems
use Synchrenity\Support\SynchrenityEventDispatcher;
use Synchrenity\Support\SynchrenityMiddlewareManager;
use Synchrenity\Security\SynchrenitySecurityManager;
use Synchrenity\Support\SynchrenityLogger;

$eventDispatcher = new SynchrenityEventDispatcher();
$middlewareManager = new SynchrenityMiddlewareManager();
$securityManager = new SynchrenitySecurityManager([
    'encryption_key' => getenv('SYNCHRENITY_KEY') ?: bin2hex(random_bytes(32)),
    'hasher' => 'bcrypt'
]);

// Integrate systems
$middlewareManager->attachToDispatcher($eventDispatcher);
$securityManager->attachMiddlewareManager($middlewareManager);

// Register default security hooks and middleware
$middlewareManager->registerSecurityHook(function($payload, $context) use ($securityManager) {
    // Example: CSRF protection
    if (isset($payload['csrf_token']) && !$securityManager->protectCSRF($payload['csrf_token'])) {
        http_response_code(403);
        echo "<h1>Forbidden: CSRF validation failed</h1>";
        return false;
    }
    // Example: XSS protection
    if (isset($payload['input'])) {
        $payload['input'] = $securityManager->protectXSS($payload['input']);
    }
    return true;
});

// --- Advanced Rate Limiter Integration ---
// Use config objects loaded by bootstrap/app.php or fallback to require if not present
use Synchrenity\API\SynchrenityApiRateLimiter;
$apiRateLimitsConfig = $apiRateLimitsConfig ?? (file_exists(__DIR__ . '/../config/api_rate_limits.php') ? require __DIR__ . '/../config/api_rate_limits.php' : null);
$oauth2Config = $oauth2Config ?? (file_exists(__DIR__ . '/../config/oauth2.php') ? require __DIR__ . '/../config/oauth2.php' : null);
$rateLimiter = new SynchrenityApiRateLimiter(
    $apiRateLimitsConfig ? $apiRateLimitsConfig->all() : [],
    [],
    function($user, $role, $endpoint) use ($apiRateLimitsConfig) {
        $conf = $apiRateLimitsConfig ? $apiRateLimitsConfig->get($endpoint, $role) : null;
        return is_array($conf) ? $conf : null;
    }
);
// Only set audit trail and event hooks if $core is defined
if (isset($core) && method_exists($core, 'audit')) $rateLimiter->setAuditTrail($core->audit());
$rateLimiter->on('allowed', function($data, $limiter) use (&$core) {
    if (isset($core) && method_exists($core, 'audit')) $core->audit()->log('rate.allowed', $data);
});
$rateLimiter->on('blocked', function($data, $limiter) use (&$core) {
    if (isset($core) && method_exists($core, 'audit')) $core->audit()->log('rate.blocked', $data);
    http_response_code(429);
    header('X-RateLimit-Limit: ' . ($data['limit'] ?? ''));
    header('X-RateLimit-Remaining: 0');
    header('X-RateLimit-Reset: ' . (($data['window'] ?? 60) - ($data['count'] ?? 0)));
    echo "<h1>Too Many Requests</h1>";
    exit(1);
});
$rateLimiter->registerPlugin(new class {
    public function onAllowed($user, $role, $endpoint, $limiter) {}
    public function onBlocked($user, $role, $endpoint, $limiter) {
        if ($limiter->getMetrics()['blocked'] > 10) {
            // Example: trigger alert or reload config
        }
    }
});
if (getenv('SYNCHRENITY_DEV') === '1') {
    if (isset($_GET['__reload_limits'])) {
        $limitsFile = __DIR__ . '/../config/rate_limits.json';
        if (file_exists($limitsFile)) {
            $limits = json_decode(file_get_contents($limitsFile), true);
            if (is_array($limits)) {
                foreach ($limits as $ep => $roles) {
                    foreach ($roles as $role => $conf) {
                        $rateLimiter->setLimit($ep, $role, $conf['limit'], $conf['window']);
                    }
                }
            }
        }
    }
}
$middlewareManager->registerGlobal(function($payload, $context) use ($rateLimiter, $apiRateLimitsConfig, $oauth2Config) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user = $context['user'] ?? $ip;
    $role = $context['role'] ?? 'guest';
    $endpoint = $_SERVER['REQUEST_URI'] ?? 'default';
    $allowed = $rateLimiter->check($user, $role, $endpoint);
    // Add rate limit headers for all responses
    $meta = $rateLimiter->getContext('meta', []);
    header('X-RateLimit-Limit: ' . ($meta['limit'] ?? ''));
    header('X-RateLimit-Remaining: ' . max(0, ($meta['limit'] ?? 0) - ($meta['count'] ?? 0)));
    header('X-RateLimit-Reset: ' . ($meta['window'] ?? 60));
    // Expose config/metrics/plugins/events in dev mode via headers (truncated for brevity)
    if (getenv('SYNCHRENITY_DEV') === '1') {
        header('X-RateLimit-Plugins: ' . substr(json_encode(array_map('get_class', $rateLimiter->getPlugins())), 0, 128));
        header('X-RateLimit-Events: ' . substr(json_encode(array_keys($rateLimiter->getEvents())), 0, 128));
        header('X-OAuth2-Plugins: ' . substr(json_encode(array_map('get_class', $oauth2Config->getPlugins())), 0, 128));
        header('X-OAuth2-Events: ' . substr(json_encode(array_keys($oauth2Config->getEvents())), 0, 128));
        header('X-RateLimit-Metrics: ' . substr(json_encode($rateLimiter->getMetrics()), 0, 128));
        header('X-OAuth2-Metrics: ' . substr(json_encode($oauth2Config->getMetrics()), 0, 128));
    }
    if (!$allowed) {
        // Blocked: event already handled
        return false;
    }
    return true;
}, 1);
// Metrics/config endpoints for observability/devops
if (isset($_SERVER['REQUEST_URI'])) {
    $uri = $_SERVER['REQUEST_URI'];
    if (preg_match('#^/(?:__metrics|__rate-limiter)$#', $uri)) {
        header('Content-Type: application/json');
        echo json_encode([
            'metrics' => $rateLimiter->getMetrics(),
            'analytics' => $rateLimiter->exportAnalytics(),
            'plugins' => array_map('get_class', $rateLimiter->getPlugins()),
            'events' => array_keys($rateLimiter->getEvents()),
            'rate_limits_config' => $apiRateLimitsConfig->all(),
        ], JSON_PRETTY_PRINT);
        exit(0);
    }
    if (preg_match('#^/__oauth2_config$#', $uri)) {
        header('Content-Type: application/json');
        echo json_encode([
            'oauth2_config' => $oauth2Config->all(),
            'plugins' => array_map('get_class', $oauth2Config->getPlugins()),
            'events' => array_keys($oauth2Config->getEvents()),
            'metrics' => $oauth2Config->getMetrics(),
        ], JSON_PRETTY_PRINT);
        exit(0);
    }
    if (preg_match('#^/__config$#', $uri)) {
        // Expose all core config objects, plugins, events, metrics for devops/inspection
        header('Content-Type: application/json');
        $coreConfigs = [
            'rate_limits' => $apiRateLimitsConfig->all(),
            'rate_limits_plugins' => array_map('get_class', $rateLimiter->getPlugins()),
            'rate_limits_events' => array_keys($rateLimiter->getEvents()),
            'rate_limits_metrics' => $rateLimiter->getMetrics(),
            'oauth2' => $oauth2Config->all(),
            'oauth2_plugins' => array_map('get_class', $oauth2Config->getPlugins()),
            'oauth2_events' => array_keys($oauth2Config->getEvents()),
            'oauth2_metrics' => $oauth2Config->getMetrics(),
        ];
        echo json_encode($coreConfigs, JSON_PRETTY_PRINT);
        exit(0);
    }
}
// Hot-reload for rate limits and OAuth2 config in dev
if (getenv('SYNCHRENITY_DEV') === '1') {
    if (isset($_GET['__reload_limits'])) {
        $apiRateLimitsConfig->reload();
    }
    if (isset($_GET['__reload_oauth2'])) {
        $oauth2Config->reload();
    }
}

// Make systems available to the core
// Load or instantiate SynchrenityCore
if (file_exists(__DIR__ . '/../bootstrap/app.php')) {
    $core = require_once __DIR__ . '/../bootstrap/app.php';
} else {
    $core = new \Synchrenity\SynchrenityCore();
}
if (method_exists($core, 'setEventDispatcher')) $core->setEventDispatcher($eventDispatcher);
if (method_exists($core, 'setMiddlewareManager')) $core->setMiddlewareManager($middlewareManager);
if (method_exists($core, 'setSecurityManager')) $core->setSecurityManager($securityManager);

// Register lifecycle hooks (boot/shutdown)
$core->onLifecycle('boot', function($core) {
    $core->audit()->log('system.boot', [
        'timestamp' => time(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
});
$core->onLifecycle('shutdown', function($core) {
    $core->audit()->log('system.shutdown', [
        'timestamp' => time(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
});

// Example: Register a dynamic module (custom logger)
if (class_exists('Synchrenity\Support\SynchrenityLogger')) {
    $logger = new SynchrenityLogger();
    $core->registerModule('logger', $logger);
}

// --- Handle the request ---
try {
    if ($core instanceof \Synchrenity\SynchrenityCore && method_exists($core, 'handleRequest') && method_exists($core, 'audit')) {
        $core->handleRequest();
        $core->audit()->log('request.completed', [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'status' => http_response_code(),
            'rate_metrics' => $rateLimiter->getMetrics(),
            'rate_analytics' => $rateLimiter->exportAnalytics('json')
        ]);
        $core->shutdown();
    } else {
        error_log('[Synchrenity Error] Core instance not found or invalid. Attempting direct instantiation.');
        $core = new \Synchrenity\SynchrenityCore();
        if (method_exists($core, 'handleRequest') && method_exists($core, 'audit')) {
            $core->handleRequest();
            $core->audit()->log('request.completed', [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'status' => http_response_code(),
                'rate_metrics' => $rateLimiter->getMetrics(),
                'rate_analytics' => $rateLimiter->exportAnalytics('json')
            ]);
            $core->shutdown();
        } else {
            error_log('[Synchrenity Error] SynchrenityCore methods missing after direct instantiation.');
            http_response_code(500);
            echo "<h1>SynchrenityCore methods missing</h1>";
            exit(1);
        }
    }
} catch (Throwable $e) {
    if (isset($core) && method_exists($core, 'audit')) {
        $core->audit()->log('error', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'rate_metrics' => isset($rateLimiter) ? $rateLimiter->getMetrics() : null,
            'rate_analytics' => isset($rateLimiter) ? $rateLimiter->exportAnalytics('json') : null
        ]);
    }
    error_log('[Synchrenity Error] ' . $e->getMessage());
    http_response_code(500);
    echo "<h1>Internal Server Error</h1>";
    exit(1);
}
