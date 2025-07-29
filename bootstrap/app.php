
<?php
// bootstrap/app.php

// Synchrenity Bootstrap File
// This file initializes the framework, loads configuration, and returns the core instance.

// Autoload dependencies using Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variable helper and .env support
require_once __DIR__ . '/../lib/Helpers/env.php';

// Load Synchrenity service container and facades automatically
require_once __DIR__ . '/../config/services.php';

// Load application configuration settings
$config = require_once __DIR__ . '/../config/app.php';

// Load environment-specific config if available
$env = getenv('APP_ENV') ?: 'production';
$envConfigFile = __DIR__ . "/../config/app.$env.php";
if (file_exists($envConfigFile)) {
    $envConfig = require $envConfigFile;
    $config = array_merge($config, $envConfig);
}

// Optionally load additional config (plugins, i18n, security, etc.)
$pluginConfig = file_exists(__DIR__ . '/../config/plugins.php') ? require __DIR__ . '/../config/plugins.php' : [];
$i18nConfig = file_exists(__DIR__ . '/../config/i18n.php') ? require __DIR__ . '/../config/i18n.php' : [];
$securityConfig = file_exists(__DIR__ . '/../config/security.php') ? require __DIR__ . '/../config/security.php' : [];

$config = array_merge($config, $pluginConfig, $i18nConfig, $securityConfig);

// Initialize the Synchrenity core framework
$core = new \Synchrenity\SynchrenityCore($config);

// Strict error reporting in dev
if ($env === 'dev') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Register plugins if plugin manager exists
if (class_exists('Synchrenity\\Plugins\\SynchrenityPluginManager')) {
    $pluginManager = new \Synchrenity\Plugins\SynchrenityPluginManager();
    // Auto-discover plugins from plugins/ directory if it exists
    $pluginsDir = __DIR__ . '/../plugins';
    if (is_dir($pluginsDir)) {
        $pluginManager->discover($pluginsDir);
    }
    $core->registerModule('pluginManager', $pluginManager);
    $pluginManager->boot();
}

// Register logger if available
if (class_exists('Synchrenity\\Support\\SynchrenityLogger')) {
    $logger = new \Synchrenity\Support\SynchrenityLogger($config);
    $core->registerModule('logger', $logger);
}

// Register health check/diagnostics if available
if (class_exists('Synchrenity\\Support\\SynchrenityHealthCheck')) {
    $health = new \Synchrenity\Support\SynchrenityHealthCheck($core);
    $core->registerModule('health', $health);
}

// Set up global error/exception handler if available
if (class_exists('Synchrenity\\ErrorHandler\\SynchrenityErrorHandler')) {
    $errorHandler = new \Synchrenity\ErrorHandler\SynchrenityErrorHandler($config);
    $errorHandler->register();
    $core->setErrorHandler([$errorHandler, 'handle']);
}

// Register test utilities in dev environment
if ($env === 'dev' && class_exists('Synchrenity\\Testing\\SynchrenityTestUtils')) {
    $core->registerModule('testUtils', new \Synchrenity\Testing\SynchrenityTestUtils());
}

// Dispatch a bootstrapped event for modules/plugins to hook into
if (method_exists($core, 'dispatch')) {
    $core->dispatch('bootstrapped', $core);
}

// Return the core instance to be used by the public entry point
return $core;
