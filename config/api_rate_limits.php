<?php
// config/api_rate_limits.php
// --- Advanced API Rate Limits Config: plugin/event/metrics/context/introspection, hot-reload, dynamic policies, anomaly detection ---
class SynchrenityApiRateLimitsConfig {
    protected $limits = [];
    protected $plugins = [];
    protected $events = [];
    protected $metrics = [ 'gets' => 0, 'reloads' => 0 ];
    protected $context = [];
    public function __construct($limits) { $this->limits = $limits; }
    public function get($endpoint, $role = 'default', $default = null) {
        $this->metrics['gets']++;
        $conf = $this->limits[$endpoint][$role] ?? $this->limits['default']['default'] ?? $default;
        $this->triggerEvent('get', compact('endpoint','role','conf'));
        return $conf;
    }
    public function set($endpoint, $role, $conf) {
        $this->limits[$endpoint][$role] = $conf;
        $this->triggerEvent('set', compact('endpoint','role','conf'));
    }
    public function reload() {
        $this->metrics['reloads']++;
        // Could reload from file, DB, or remote API
        $this->triggerEvent('reload', $this->limits);
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
    public function all() { return $this->limits; }
}

$limits = [
    'default' => [
        'default' => [ 'limit' => 100, 'window' => 60, 'burst' => 20, 'burstWindow' => 10 ]
    ],
    'GET:/api/resource' => [
        'user' => [ 'limit' => 50, 'window' => 60, 'burst' => 10, 'burstWindow' => 10 ],
        'admin' => [ 'limit' => 200, 'window' => 60, 'burst' => 40, 'burstWindow' => 10 ]
    ]
    // Add more endpoint/role configs as needed
];

$apiRateLimitsConfig = new SynchrenityApiRateLimitsConfig($limits);
// Example: plugin for anomaly detection
$apiRateLimitsConfig->on('get', function($data, $cfg) {
    if (($data['conf']['limit'] ?? 0) > 1000) error_log('[Synchrenity] Unusually high rate limit for ' . $data['endpoint']);
});
// Example: hot-reload in dev
if (getenv('SYNCHRENITY_DEV') === '1' && isset($_GET['__reload_limits'])) {
    $apiRateLimitsConfig->reload();
}

return $apiRateLimitsConfig;
