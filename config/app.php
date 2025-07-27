

<?php
// config/app.php

// Synchrenity Application Configuration
// This file contains important settings for your application and framework.
// Use environment variables for sensitive or environment-specific values.

if (!function_exists('synchrenity_env')) {
    function synchrenity_env($file)
    {
        $env = [];
        if (!file_exists($file)) return $env;
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (!strpos($line, '=')) continue;
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);
            if (preg_match('/^("|\').*\1$/', $val)) {
                $val = substr($val, 1, -1);
            }
            $val = preg_replace_callback('/\${([A-Z0-9_]+)}/', function($m) use ($env) {
                return $env[$m[1]] ?? '';
            }, $val);
            if (strtolower($val) === 'true') $val = true;
            elseif (strtolower($val) === 'false') $val = false;
            elseif (is_numeric($val)) $val = $val + 0;
            $env[$key] = $val;
        }
        return $env;
    }
}
$env = synchrenity_env(__DIR__ . '/../.env');

return [
    // Application name and branding
    'name' => 'Synchrenity',

    // Environment settings
    'env' => $env['APP_ENV'] ?? 'development', // development, production, etc.
    'debug' => $env['APP_DEBUG'] ?? true,      // Enable debug mode
    'url' => $env['APP_URL'] ?? 'http://localhost', // Base URL

    // Localization and timezone
    'timezone' => 'UTC',
    'locale' => 'en',
    'fallback_locale' => 'en',

    // Security
    'key' => $env['APP_KEY'] ?? '',           // Application encryption key
    'cipher' => 'AES-256-CBC',             // Encryption cipher

    // Service providers for framework features
    'providers' => [
        // Framework Service Providers...
    ],

    // Class aliases for easier usage
    'aliases' => [
        // Class Aliases...
    ],
];
