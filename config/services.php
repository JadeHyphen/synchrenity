<?php
// Example of registering services in Synchrenity
use Synchrenity\Support\SynchrenityServiceContainer;
use Synchrenity\Auth\Auth;
use Synchrenity\ORM\Atlas;
use Synchrenity\RateLimit\SynchrenityRateLimiter;

// --- Advanced Service Container: plugin/event/metrics/context/introspection, hot-reload, dynamic DI, robust UX ---
class SynchrenityServiceContainerExt extends SynchrenityServiceContainer {
    protected $plugins = [];
    protected $events = [];
    protected $metrics = [ 'gets' => 0, 'sets' => 0, 'reloads' => 0 ];
    protected $context = [];
    protected $serviceKeys = [];
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
    public function keys() { return $this->serviceKeys; }
    public function register($id, $service) { $this->serviceKeys[] = $id; return parent::register($id, $service); }
    public function singleton(string $id, callable $service): void { $this->serviceKeys[] = $id; parent::singleton($id, $service); }
    public function get($id) { $this->metrics['gets']++; $this->triggerEvent('get', $id); return parent::get($id); }
    public function reload() { $this->metrics['reloads']++; $this->triggerEvent('reload', $this->keys()); }
}

$synchrenityContainer = new SynchrenityServiceContainerExt();

// Register core services
$synchrenityContainer->register('auth', function($container) {
    return new \Synchrenity\Auth\SynchrenityAuth();
});
$synchrenityContainer->register('atlas', function($container) {
    // Use the SynchrenityAtlas class from the correct namespace
    // Get PDO configuration from environment or config
    $config = [
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'dbname' => $_ENV['DB_NAME'] ?? 'synchrenity',
        'username' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4'
    ];
    
    try {
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
        $pdo = new \PDO($dsn, $config['username'], $config['password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false
        ]);
    } catch (\PDOException $e) {
        // Fallback to SQLite for development if MySQL fails
        $pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ]);
        error_log("MySQL connection failed, using SQLite fallback: " . $e->getMessage());
    }
    
    return new \Synchrenity\Atlas\SynchrenityAtlas($pdo);
});
$synchrenityContainer->singleton('policy', function() {
    return new \Synchrenity\Security\SynchrenityPolicyManager();
});
$synchrenityContainer->register('api_rate_limits', function($container) {
    // Load the API rate limits config
    return require __DIR__ . '/api_rate_limits.php';
});
$synchrenityContainer->register('rate_limiter', function($container) {
    $rateLimiter = new SynchrenityRateLimiter();
    // Inject the API rate limits config
    $apiRateLimitsConfig = $container->get('api_rate_limits');
    $rateLimiter->setApiRateLimitsConfig($apiRateLimitsConfig);
    return $rateLimiter;
});

// Example: dynamic service resolution
$synchrenityContainer->register('oauth2', function($container) {
    return file_exists(__DIR__ . '/oauth2.php') ? require __DIR__ . '/oauth2.php' : null;
});

// Example: plugin for metrics
$synchrenityContainer->on('get', function($id, $c) {
    if (function_exists('error_log')) error_log('[Synchrenity] Service resolved: ' . $id);
});

// Set container for facades
Auth::setContainer($synchrenityContainer);
Atlas::setContainer($synchrenityContainer);

// Usage examples:
// $user = Auth::user();
// $table = Atlas::table('users');
// $rateLimiter = $synchrenityContainer->get('rate_limiter');
// $apiLimits = $synchrenityContainer->get('api_rate_limits');
// $limitConf = $apiLimits->get('GET:/api/resource', 'user');
// $allowed = $rateLimiter->check('user123', 'GET:/api/resource');
