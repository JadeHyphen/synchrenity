<?php

declare(strict_types=1);

/**
 * Synchrenity Bootstrap File
 * 
 * Simplified bootstrap that focuses on core framework setup.
 * Application logic has been moved to SynchrenityApplication.
 */

// Autoload dependencies using Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variable helper if exists
if (file_exists(__DIR__ . '/../lib/Helpers/env.php')) {
    require_once __DIR__ . '/../lib/Helpers/env.php';
}

// Load service container if exists
if (file_exists(__DIR__ . '/../config/services.php')) {
    require_once __DIR__ . '/../config/services.php';
}

try {
    // Load application configuration
    $config = [];
    
    // Load main app config
    if (file_exists(__DIR__ . '/../config/app.php')) {
        $appConfig = require __DIR__ . '/../config/app.php';
        
        if (is_array($appConfig)) {
            $config = array_merge($config, $appConfig);
        } elseif (is_object($appConfig) && method_exists($appConfig, 'all')) {
            // Handle config objects
            $config = array_merge($config, $appConfig->all());
            
            // Handle environment variables if config object supports it
            if (method_exists($appConfig, 'envAll')) {
                $envVars = $appConfig->envAll();
                if (is_array($envVars)) {
                    foreach ($envVars as $key => $value) {
                        if (!getenv($key)) {
                            putenv("{$key}={$value}");
                        }
                    }
                }
            }
        }
    }
    
    // Load environment-specific config
    $env = getenv('APP_ENV') ?: ($config['env'] ?? 'production');
    $envConfigFile = __DIR__ . "/../config/app.{$env}.php";
    if (file_exists($envConfigFile)) {
        $envConfig = require $envConfigFile;
        if (is_array($envConfig)) {
            $config = array_merge($config, $envConfig);
        }
    }
    
    // Load additional configuration files
    $additionalConfigs = ['plugins', 'i18n', 'security'];
    foreach ($additionalConfigs as $configName) {
        $configFile = __DIR__ . "/../config/{$configName}.php";
        if (file_exists($configFile)) {
            $additionalConfig = require $configFile;
            if (is_array($additionalConfig)) {
                $config = array_merge($config, $additionalConfig);
            }
        }
    }
    
    // Initialize the Synchrenity core framework
    $core = new \Synchrenity\SynchrenityCore($config, $_ENV);
    
    // Register core modules
    $coreModules = [
        'pluginManager' => 'Synchrenity\\Plugins\\SynchrenityPluginManager',
        'logger' => 'Synchrenity\\Support\\SynchrenityLogger',
        'health' => 'Synchrenity\\Support\\SynchrenityHealthCheck',
        'errorHandler' => 'Synchrenity\\ErrorHandler\\SynchrenityErrorHandler',
    ];
    
    foreach ($coreModules as $name => $className) {
        if (class_exists($className)) {
            try {
                if ($name === 'logger') {
                    // Special handling for logger with log directory
                    $logDir = __DIR__ . '/../storage/logs';
                    if (!is_dir($logDir)) {
                        mkdir($logDir, 0755, true);
                    }
                    $instance = new $className($logDir . '/app.log');
                } elseif ($name === 'health') {
                    $instance = new $className($core);
                } elseif ($name === 'errorHandler') {
                    $instance = new $className($config);
                    if (method_exists($instance, 'register')) {
                        $instance->register();
                    }
                    if (method_exists($core, 'setErrorHandler')) {
                        $core->setErrorHandler([$instance, 'handle']);
                    }
                } else {
                    $instance = new $className();
                }
                
                $core->registerModule($name, $instance);
                
                // Auto-discover plugins if this is the plugin manager
                if ($name === 'pluginManager') {
                    $pluginsDir = __DIR__ . '/../plugins';
                    if (is_dir($pluginsDir) && method_exists($instance, 'discover')) {
                        $instance->discover($pluginsDir);
                    }
                    if (method_exists($instance, 'boot')) {
                        $instance->boot();
                    }
                }
                
            } catch (\Throwable $e) {
                error_log("Failed to register module {$name}: " . $e->getMessage());
            }
        }
    }
    
    // Register test utilities in development environment
    if (in_array($env, ['dev', 'development', 'local'], true) && 
        class_exists('Synchrenity\\Testing\\SynchrenityTestUtils')) {
        try {
            $core->registerModule('testUtils', new \Synchrenity\Testing\SynchrenityTestUtils());
        } catch (\Throwable $e) {
            error_log("Failed to register test utilities: " . $e->getMessage());
        }
    }
    
    // Dispatch bootstrapped event
    if (method_exists($core, 'dispatch')) {
        $core->dispatch('bootstrapped', $core);
    }
    
    // Return the core instance
    return $core;
    
} catch (\Throwable $e) {
    error_log('[Synchrenity Bootstrap Error] ' . $e->getMessage());
    
    // Fallback: return a minimal core instance
    return new \Synchrenity\SynchrenityCore([], []);
}