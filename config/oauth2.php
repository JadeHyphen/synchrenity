<?php
// config/oauth2.php
// --- Advanced OAuth2 Config: plugin/event/metrics/context/introspection, multi-provider, PKCE, scopes, dynamic config, secrets, hot-reload ---
class SynchrenityOAuth2Config {
    protected $providers = [];
    protected $plugins = [];
    protected $events = [];
    protected $metrics = [ 'gets' => 0, 'reloads' => 0 ];
    protected $context = [];
    public function __construct($providers) {
        $this->providers = $providers;
    }
    public function get($provider, $key = null, $default = null) {
        $this->metrics['gets']++;
        $conf = $this->providers[$provider] ?? [];
        $this->triggerEvent('get', compact('provider','key','conf'));
        if ($key === null) return $conf;
        return $conf[$key] ?? $default;
    }
    public function set($provider, $key, $value) {
        $this->providers[$provider][$key] = $value;
        $this->triggerEvent('set', compact('provider','key','value'));
    }
    public function reload() {
        $this->metrics['reloads']++;
        // Could reload from file, DB, or remote API
        $this->triggerEvent('reload', $this->providers);
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
    public function all() { return $this->providers; }
}

$providers = [
    'google' => [
        'auth_url' => 'https://accounts.google.com/o/oauth2/auth',
        'token_url' => 'https://oauth2.googleapis.com/token',
        'userinfo_url' => 'https://openidconnect.googleapis.com/v1/userinfo',
        'client_id' => getenv('GOOGLE_CLIENT_ID'),
        'client_secret' => getenv('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => getenv('GOOGLE_REDIRECT_URI'),
        'scopes' => ['openid','email','profile'],
        'pkce' => true,
        'discovery_url' => 'https://accounts.google.com/.well-known/openid-configuration',
        'enabled' => true
    ],
    'github' => [
        'auth_url' => 'https://github.com/login/oauth/authorize',
        'token_url' => 'https://github.com/login/oauth/access_token',
        'client_id' => getenv('GITHUB_CLIENT_ID'),
        'client_secret' => getenv('GITHUB_CLIENT_SECRET'),
        'redirect_uri' => getenv('GITHUB_REDIRECT_URI'),
        'scopes' => ['read:user','user:email'],
        'pkce' => false,
        'enabled' => true
    ],
    // Add more providers as needed
];

$oauth2Config = new SynchrenityOAuth2Config($providers);
// Example: plugin for metrics
$oauth2Config->on('get', function($data, $cfg) {
    if (function_exists('error_log')) error_log('[Synchrenity] OAuth2 config accessed for ' . $data['provider']);
});
// Example: hot-reload in dev
if (getenv('SYNCHRENITY_DEV') === '1' && isset($_GET['__reload_oauth2'])) {
    $oauth2Config->reload();
}

return $oauth2Config;
