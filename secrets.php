<?php
declare(strict_types=1);
/**
 * Synchrenity secrets (override sensitive config here)
 *
 * This file returns a type-safe array of sensitive configuration options, supporting
 * environment variable overrides and best practices for modern PHP frameworks.
 *
 * @return array<string, mixed> Synchrenity secrets
 */

// Helper to get env var with default and type cast (copied from config.php for consistency)
if (!function_exists('env')) {
    /**
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
            return is_numeric($value) ? (int)$value : $default;
        }
        if (is_float($default)) {
            return is_numeric($value) ? (float)$value : $default;
        }
        return $value;
    }
}

return [
    // Application encryption key (required for production)
    'APP_KEY' => env('APP_KEY', ''), // e.g. base64:...

    // Database credentials
    'DB_PASSWORD' => env('DB_PASSWORD', ''),
    'DB_USERNAME' => env('DB_USERNAME', ''),

    // Mailer credentials
    'MAIL_PASSWORD' => env('MAIL_PASSWORD', ''),
    'MAIL_USERNAME' => env('MAIL_USERNAME', ''),

    // Optional: SMTP encryption
    'MAIL_ENCRYPTION' => env('MAIL_ENCRYPTION', ''),

    // Add more secrets as needed, matching config.php keys for sensitive data
];
