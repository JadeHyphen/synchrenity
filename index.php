<?php

declare(strict_types=1);

/**
 * Synchrenity Framework Entry Point
 * 
 * Clean, minimal entry point that delegates all logic to the framework
 * This file should contain no application logic - just bootstrap and run
 */

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables if .env exists
if (file_exists(__DIR__ . '/.env') && function_exists('parse_ini_file')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    if ($env) {
        foreach ($env as $key => $value) {
            if (!getenv($key)) {
                putenv("{$key}={$value}");
            }
        }
    }
}

try {
    // Load application configuration
    $config = [];
    
    // Load main config
    if (file_exists(__DIR__ . '/config/app.php')) {
        $appConfig = require __DIR__ . '/config/app.php';
        if (is_array($appConfig)) {
            $config = array_merge($config, $appConfig);
        } elseif (is_object($appConfig) && method_exists($appConfig, 'all')) {
            $config = array_merge($config, $appConfig->all());
        }
    }
    
    // Load environment-specific config
    $env = getenv('APP_ENV') ?: ($config['env'] ?? 'production');
    $envConfigFile = __DIR__ . "/config/app.{$env}.php";
    if (file_exists($envConfigFile)) {
        $envConfig = require $envConfigFile;
        if (is_array($envConfig)) {
            $config = array_merge($config, $envConfig);
        }
    }
    
    // Create and boot the application
    $app = new \Synchrenity\SynchrenityApplication($config);
    $app->boot();
    
    // Handle the request
    $app->handleRequest();
    
    // Graceful shutdown
    $app->shutdown();
    
} catch (\Throwable $e) {
    // Final fallback error handling
    error_log('[Synchrenity Fatal] ' . $e->getMessage());
    
    if (!headers_sent()) {
        http_response_code(500);
        
        // Show error details only in development
        if (in_array(getenv('APP_ENV'), ['development', 'dev', 'local'], true)) {
            echo "<h1>Application Error</h1>";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        } else {
            echo "<h1>Internal Server Error</h1>";
        }
    }
    
    exit(1);
}