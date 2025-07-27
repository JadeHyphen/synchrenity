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

require_once __DIR__ . '/../lib/Support/SynchrenityEventDispatcher.php';
require_once __DIR__ . '/../lib/Support/SynchrenityMiddlewareManager.php';
require_once __DIR__ . '/../lib/Security/SynchrenitySecurityManager.php';

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
$core = require_once __DIR__ . '/../bootstrap/app.php';
if (method_exists($core, 'setEventDispatcher')) $core->setEventDispatcher($eventDispatcher);
if (method_exists($core, 'setMiddlewareManager')) $core->setMiddlewareManager($middlewareManager);
if (method_exists($core, 'setSecurityManager')) $core->setSecurityManager($securityManager);

// --- Handle the request ---
if ($core instanceof \Synchrenity\SynchrenityCore) {
    try {
        $core->handleRequest();
    } catch (Throwable $e) {
        error_log('[Synchrenity Error] ' . $e->getMessage());
        http_response_code(500);
        echo "<h1>Internal Server Error</h1>";
        exit(1);
    }
} else {
    error_log('[Synchrenity Error] Core instance not found or invalid.');
    http_response_code(500);
    echo "<h1>Synchrenity Error</h1>";
    exit(1);
}
