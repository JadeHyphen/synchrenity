
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

// Load application configuration settings (advanced config object)
$appConfig = require_once __DIR__ . '/../config/app.php';
// Load environment-specific config if available (merge into config object)
$env = getenv('APP_ENV') ?: $appConfig->get('env', 'production');
$envConfigFile = __DIR__ . "/../config/app.$env.php";
if (file_exists($envConfigFile)) {
    $envConfig = require $envConfigFile;
    if (is_array($envConfig)) {
        foreach ($envConfig as $k => $v) $appConfig->set($k, $v);
    }
}
// Optionally load additional config (plugins, i18n, security, etc.)
$pluginConfig = file_exists(__DIR__ . '/../config/plugins.php') ? require __DIR__ . '/../config/plugins.php' : [];
$i18nConfig = file_exists(__DIR__ . '/../config/i18n.php') ? require __DIR__ . '/../config/i18n.php' : [];
$securityConfig = file_exists(__DIR__ . '/../config/security.php') ? require __DIR__ . '/../config/security.php' : [];
foreach ([$pluginConfig, $i18nConfig, $securityConfig] as $cfg) {
    if (is_array($cfg)) foreach ($cfg as $k => $v) $appConfig->set($k, $v);
}
// Hot-reload config in dev mode
if ($appConfig->featureEnabled('hot_reload') && isset($_GET['__reload_config'])) {
    $appConfig->reload();
}
// Initialize the Synchrenity core framework
// Pass the config array, not the SynchrenityAppConfig object, to match the constructor
$core = new \Synchrenity\SynchrenityCore($appConfig->all(), $appConfig->envAll());

// Strict error reporting in dev
if ($env === 'dev' || $appConfig->get('debug')) {
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
    // Register plugin manager as config plugin
    if (method_exists($appConfig, 'registerPlugin')) $appConfig->registerPlugin($pluginManager);
}

// Register logger if available
if (class_exists('Synchrenity\\Support\\SynchrenityLogger')) {
    $logFile = __DIR__ . '/../storage/logs/app.log';
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0777, true);
    }
    $logger = new \Synchrenity\Support\SynchrenityLogger($logFile);
    $core->registerModule('logger', $logger);
    if (method_exists($appConfig, 'registerPlugin')) $appConfig->registerPlugin($logger);
}

// Register health check/diagnostics if available
if (class_exists('Synchrenity\\Support\\SynchrenityHealthCheck')) {
    $health = new \Synchrenity\Support\SynchrenityHealthCheck($core);
    $core->registerModule('health', $health);
    if (method_exists($appConfig, 'registerPlugin')) $appConfig->registerPlugin($health);
}

// Set up global error/exception handler if available
if (class_exists('Synchrenity\\ErrorHandler\\SynchrenityErrorHandler')) {
    $errorHandler = new \Synchrenity\ErrorHandler\SynchrenityErrorHandler($appConfig->all());
    $errorHandler->register();
    $core->setErrorHandler([$errorHandler, 'handle']);
    if (method_exists($appConfig, 'registerPlugin')) $appConfig->registerPlugin($errorHandler);
}

// Register test utilities in dev environment
if (($env === 'dev' || $appConfig->get('debug')) && class_exists('Synchrenity\\Testing\\SynchrenityTestUtils')) {
    $core->registerModule('testUtils', new \Synchrenity\Testing\SynchrenityTestUtils());
}

// Dispatch a bootstrapped event for modules/plugins to hook into
if (method_exists($core, 'dispatch')) {
    $core->dispatch('bootstrapped', $core);
    // Do not call protected triggerEvent directly on $appConfig
}

// Return the core instance to be used by the public entry point
return $core;
