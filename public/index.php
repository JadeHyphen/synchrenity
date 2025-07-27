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
use Synchrenity\Support\SynchrenityEventDispatcher;
use Synchrenity\Support\SynchrenityMiddlewareManager;
use Synchrenity\Security\SynchrenitySecurityManager;
use Synchrenity\Support\SynchrenityLogger;

require_once __DIR__ . '/../lib/Support/SynchrenityEventDispatcher.php';
require_once __DIR__ . '/../lib/Support/SynchrenityMiddlewareManager.php';
require_once __DIR__ . '/../lib/Security/SynchrenitySecurityManager.php';
require_once __DIR__ . '/../lib/Support/SynchrenityLogger.php';
require_once __DIR__ . '/../lib/SynchrenityCore.php';
if (!class_exists('Synchrenity\\SynchrenityCore')) {
    error_log('[Synchrenity Error] SynchrenityCore class not found after require. Check namespace and autoloading.');
    http_response_code(500);
    echo "<h1>SynchrenityCore class not found</h1>";
    exit(1);
}

// Initialize core systems
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

// Optionally, register global middleware (logging, rate limiting, etc.)
$middlewareManager->registerGlobal(function($payload, $context) use ($securityManager) {
    // Example: Rate limiting
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!$securityManager->rateLimit($ip, 100, 60)) {
        http_response_code(429);
        echo "<h1>Too Many Requests</h1>";
        return false;
    }
    return true;
}, 1);

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
if ($core instanceof \Synchrenity\SynchrenityCore) {
    if (method_exists($core, 'handleRequest') && method_exists($core, 'audit')) {
        try {
            $core->handleRequest();
            // Audit request completion
            $core->audit()->log('request.completed', [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'status' => http_response_code()
            ]);
        } catch (Throwable $e) {
            $core->audit()->log('error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            error_log('[Synchrenity Error] ' . $e->getMessage());
            http_response_code(500);
            echo "<h1>Internal Server Error</h1>";
            exit(1);
        }
        $core->shutdown();
    } else {
        error_log('[Synchrenity Error] SynchrenityCore methods missing.');
        http_response_code(500);
        echo "<h1>SynchrenityCore methods missing</h1>";
        exit(1);
    }
} else {
    error_log('[Synchrenity Error] Core instance not found or invalid. Attempting direct instantiation.');
    $core = new \Synchrenity\SynchrenityCore();
    if (method_exists($core, 'handleRequest') && method_exists($core, 'audit')) {
        try {
            $core->handleRequest();
            $core->audit()->log('request.completed', [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'status' => http_response_code()
            ]);
        } catch (Throwable $e) {
            $core->audit()->log('error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            error_log('[Synchrenity Error] ' . $e->getMessage());
            http_response_code(500);
            echo "<h1>Internal Server Error</h1>";
            exit(1);
        }
        $core->shutdown();
    } else {
        error_log('[Synchrenity Error] SynchrenityCore methods missing after direct instantiation.');
        http_response_code(500);
        echo "<h1>SynchrenityCore methods missing</h1>";
        exit(1);
    }
}
