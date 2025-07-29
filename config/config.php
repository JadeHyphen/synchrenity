<?php declare(strict_types=1);
/**
 * Synchrenity Framework Default Configuration
 *
 * This file returns a type-safe array of configuration options, supporting
 * environment variable overrides and best practices for modern PHP frameworks.
 *
 * @return array<string, mixed> Synchrenity configuration
 */

/**
 * Get an environment variable with type casting and default fallback.
 *
 * @template T
 * @param string $key
 * @param T $default
 * @return T|string|null
 */
function env(string $key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    if (is_bool($default)) {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }
    if (is_int($default)) {
        // If the value is numeric, cast to int, else fallback to default
        return is_numeric($value) ? (int)$value : $default;
    }
    if (is_float($default)) {
        return is_numeric($value) ? (float)$value : $default;
    }
    return $value;
}

return [
    // Directory for logs (absolute path)
    'log_dir' => env('SYNCHRENITY_LOG_DIR', __DIR__ . '/../storage/logs'),

    // Application environment: development, staging, production
    'env' => env('APP_ENV', 'development'),

    // Debug mode (enables verbose error output)
    'debug' => env('APP_DEBUG', true),

    // Application URL (used for CLI, emails, etc.)
    'app_url' => env('APP_URL', 'http://localhost'),

    // Timezone
    'timezone' => env('APP_TIMEZONE', 'UTC'),

    // Locale
    'locale' => env('APP_LOCALE', 'en'),

    // Encryption key (should be set in secrets.php or .env)
    'key' => env('APP_KEY', ''),

    // Database config (example, override in secrets.php for real secrets)
    'db' => [
        'driver' => env('DB_DRIVER', 'mysql'),
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', 3306),
        'database' => env('DB_DATABASE', 'synchrenity'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
        'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
    ],

    // Mailer config (example, override in secrets.php for real secrets)
    'mail' => [
        'host' => env('MAIL_HOST', 'localhost'),
        'port' => env('MAIL_PORT', 1025),
        'username' => env('MAIL_USERNAME', null),
        'password' => env('MAIL_PASSWORD', null),
        'encryption' => env('MAIL_ENCRYPTION', null),
        'from' => env('MAIL_FROM', 'noreply@synchrenity.local'),
    ],

    // Cache config
    'cache' => [
        'driver' => env('CACHE_DRIVER', 'file'),
        'path' => env('CACHE_PATH', __DIR__ . '/../storage/cache'),
    ],

    // Session config
    'session' => [
        'driver' => env('SESSION_DRIVER', 'file'),
        'lifetime' => env('SESSION_LIFETIME', 120),
        'encrypt' => env('SESSION_ENCRYPT', false),
    ],

    // Rate limiting
    'rate_limit' => [
        'enabled' => env('RATE_LIMIT_ENABLED', true),
        'max_requests' => env('RATE_LIMIT_MAX_REQUESTS', 100),
        'window_seconds' => env('RATE_LIMIT_WINDOW_SECONDS', 60),
    ],

    // Feature flags (example)
    'features' => [
        'experimental_api' => env('FEATURE_EXPERIMENTAL_API', false),
    ],

    // Add more default config values as needed, following Synchrenity conventions
];
