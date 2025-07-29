

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
            if (preg_match('/^(["\']).*\1$/', $val)) {
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

class SynchrenityAppConfig {
    protected $config = [];
    protected $env = [];
    protected $plugins = [];
    protected $events = [];
    protected $metrics = [ 'reloads' => 0, 'gets' => 0 ];
    protected $context = [];
    public function __construct($env, $baseConfig) {
        $this->env = $env;
        $this->config = $baseConfig;
    }
    public function get($key, $default = null) {
        $this->metrics['gets']++;
        $val = $this->config[$key] ?? $default;
        $this->triggerEvent('get', compact('key','val'));
        return $val;
    }
    public function set($key, $value) {
        $this->config[$key] = $value;
        $this->triggerEvent('set', compact('key','value'));
    }
    public function reload() {
        $this->metrics['reloads']++;
        $this->env = synchrenity_env(__DIR__ . '/../.env');
        $this->triggerEvent('reload', $this->env);
    }
    public function on($event, callable $cb) { $this->events[$event][] = $cb; }
    protected function triggerEvent($event, $data = null) {
        foreach ($this->events[$event] ?? [] as $cb) call_user_func($cb, $data, $this);
    }
    public function registerPlugin($plugin) { $this->plugins[] = $plugin; }
    public function getPlugins() { return $this->plugins; }
    public function getEvents() { return $this->events; }
    public function getMetrics() { return $this->metrics; }
    public function setContext($k, $v) { $this->context[$k] = $v; }
    public function getContext($k, $d=null) { return $this->context[$k] ?? $d; }
    public function env($k, $d=null) { return $this->env[$k] ?? $d; }
    public function featureEnabled($flag) {
        return !empty($this->config['features'][$flag]) || !empty($this->env['FEATURE_' . strtoupper($flag)]);
    }
    public function secret($k) {
        // Could load from vault, env, or file
        return $this->env[$k] ?? ($this->config['secrets'][$k] ?? null);
    }
    // --- Public accessors for introspection ---
    public function all() { return $this->config; }
    public function envAll() { return $this->env; }
    public function contextAll() { return $this->context; }
}

$baseConfig = [
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

    // Feature flags
    'features' => [
        'rate_limit_metrics' => true,
        'hot_reload' => true,
        'advanced_audit' => true,
    ],

    // Secrets (could be loaded from vault)
    'secrets' => [
        // 'DB_PASSWORD' => '...'
    ],
];

$appConfig = new SynchrenityAppConfig($env, $baseConfig);
// Example: plugin for config reload audit
$appConfig->on('reload', function($env, $cfg) {
    if (function_exists('error_log')) error_log('[Synchrenity] Config reloaded');
});
// Example: plugin for feature flag audit
$appConfig->on('get', function($data, $cfg) {
    if ($data['key'] === 'features') error_log('[Synchrenity] Feature flags accessed');
});

return $appConfig;
