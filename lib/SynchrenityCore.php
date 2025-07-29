<?php

namespace Synchrenity;

class SynchrenityCore {
    /**
     * Modern logging: SynchrenityLogger instance (file, stdout, JSON, rotation, context, channels)
     */
    public $logger;

    // --- ADVANCED: Metrics/observability ---
    protected $metrics = [];
    public function recordMetric($name, $value, $tags = [])
    {
        $this->metrics[] = ['name' => $name,'value' => $value,'tags' => $tags,'time' => time()];
    }
    public function getMetrics()
    {
        return $this->metrics;
    }

    public function log($level, $msg, $context = [])
    {
        if ($this->logger) {
            return $this->logger->log($level, $msg, $context);
        }
        error_log("[$level] $msg " . json_encode($context));
    }
    // --- ADVANCED: Middleware pipeline ---
    protected $middleware = [];
    public function addMiddleware($mw)
    {
        $this->middleware[] = $mw;
    }
    public function runMiddleware($request, $final = null)
    {
        $stack = $this->middleware;
        $core  = $this;
        $index = 0;
        $next  = function ($req) use (&$stack, &$index, $final, $core, &$next) {
            if (!isset($stack[$index])) {
                return $final ? $final($req) : $req;
            }
            $mw = $stack[$index++];

            return $mw->handle($req, $next);
        };

        return $next($request);
    }

    // --- ADVANCED: Health/readiness/liveness checks ---
    public function health()
    {
        return ['status' => 'ok','uptime' => time() - ($_SERVER['REQUEST_TIME'] ?? time())];
    }
    public function readiness()
    {
        return ['ready' => true];
    }
    public function liveness()
    {
        return ['alive' => true];
    }

    // --- ADVANCED: Hot reload/config reload ---
    public function reloadConfig(array $config = [])
    {
        if (!empty($config)) {
            $this->config = $config;
        } elseif (file_exists('config/config.php')) {
            $this->config = include 'config/config.php';
        }
    }
    public function reloadEnv(array $env = [])
    {
        if (!empty($env)) {
            $this->env = $env;
        } elseif (file_exists('.env')) {
            $this->env = parse_ini_file('.env');
        }
    }

    // --- ADVANCED: Graceful shutdown (signal handling) ---
    protected $shutdownHandlers = [];
    public function onShutdown(callable $cb)
    {
        $this->shutdownHandlers[] = $cb;
    }
    public function handleSignals()
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'shutdown']);
            pcntl_signal(SIGINT, [$this, 'shutdown']);
        }
    }
    public function shutdown()
    {
        $this->runLifecycleHook('shutdown');

        foreach ($this->shutdownHandlers as $cb) {
            $cb($this);
        }
    }

    // --- ADVANCED: Secrets management ---
    protected $secrets = [];
    public function loadSecrets($source = null)
    {
        if ($source && is_array($source)) {
            $this->secrets = $source;
        } elseif (file_exists('secrets.php')) {
            $this->secrets = include 'secrets.php';
        } elseif (file_exists('.secrets.env')) {
            $this->secrets = parse_ini_file('.secrets.env');
        }
    }
    public function secret($key, $default = null)
    {
        return $this->secrets[$key] ?? $default;
    }

    // --- ADVANCED: Async event bus ---
    protected $eventQueue = [];
    public function queueEvent($event, ...$args)
    {
        $this->eventQueue[] = [$event, $args];
    }
    public function processEventQueue()
    {
        while ($evt = array_shift($this->eventQueue)) {
            $this->dispatch($evt[0], ...$evt[1]);
        }
    }

    // --- ADVANCED: Plugin/extension loader ---
    public function loadPlugin($file)
    {
        if (file_exists($file)) {
            $plugin = include $file;

            if (is_object($plugin) && method_exists($plugin, 'register')) {
                $plugin->register($this);
            }
        }
    }
    public function unloadPlugin($name)
    {
        unset($this->modules[$name]);
        unset($this->$name);
    }

    // --- ADVANCED: Security hardening ---
    public function validateRequest($request)
    {
        // Example: check for required fields, input types, etc.
        return is_array($request) && isset($request['headers']) && isset($request['body']);
    }
    public function sanitize($data)
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = $this->sanitize($v);
            }
        }

        return is_string($data) ? htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $data;
    }

    // --- ADVANCED: Multi-tenancy ---
    protected $tenantContext = null;
    public function setTenantContext($tenant)
    {
        $this->tenantContext = $tenant;
    }
    public function getTenantContext()
    {
        return $this->tenantContext;
    }

    // --- ADVANCED: Distributed locking/coordination ---
    protected $locks = [];
    public function acquireLock($name)
    {
        if (!isset($this->locks[$name])) {
            $this->locks[$name] = true;

            return true;
        }

        return false;
    }
    public function releaseLock($name)
    {
        unset($this->locks[$name]);
    }

    // --- ADVANCED: API versioning ---
    protected $apiVersion = 'v1';
    public function setApiVersion($ver)
    {
        $this->apiVersion = $ver;
    }
    public function getApiVersion()
    {
        return $this->apiVersion;
    }

    // --- ADVANCED: Self-diagnostics ---
    public function diagnostics()
    {
        return [
            'framework' => self::$frameworkName,
            'version'   => $this->getApiVersion(),
            'modules'   => array_keys($this->modules),
            'metrics'   => $this->getMetrics(),
            'uptime'    => time() - ($_SERVER['REQUEST_TIME'] ?? time()),
            'env'       => $this->env,
            'config'    => $this->config,
        ];
    }
    /**
     * Stores application configuration
     *
     * @var array
     */
    protected $config;

    /**
     * Stores loaded environment variables
     *
     * @var array
     */
    protected $env;

    /**
     * Registered service providers
     *
     * @var array
     */
    protected $providers = [];

    /**
     * Registered event listeners
     *
     * @var array
     */
    protected $events = [];

    /**
     * Framework branding name
     */
    protected static $frameworkName = 'Synchrenity';

    /**
     * Error handler callback
     */
    /**
     * Error handler instance (should be an object, not a callable)
     */
    protected $errorHandler;

    /**
     * CLI kernel instance
     */
    protected $cliKernel;

    /**
     * Audit trail instance
     */

    
    /**
     * Plugin manager module
     *
     * @var mixed|null
     */
    public $pluginManager = null;

    
    /**
     * Health check module
     *
     * @var mixed|null
     */
    public $health = null;

    
    /**
     * Test utilities module
     *
     * @var mixed|null
     */
    public $testUtils = null;

    protected $auditTrail;

    // Module properties for audit injection
    public $auth;
    public $queue;
    public $notifier;
    public $media;
    public $cache;
    public $rateLimiter;
    public $tenant;
    public $plugin;
    public $i18n;
    public $websocket;
    public $validator;
    public $apiRateLimiter;
    public $oauth2Provider;
    protected $modules        = [];
    protected $lifecycleHooks = [ 'boot' => [], 'shutdown' => [] ];

    /**
     * Register a module dynamically
     */
    public function registerModule($name, $instance)
    {
        $this->modules[$name] = $instance;

        if (method_exists($instance, 'setAuditTrail')) {
            $instance->setAuditTrail($this->auditTrail);
        }
        $this->$name = $instance;
    }

    /**
     * Get a registered module
     */
    public function getModule($name)
    {
        return $this->modules[$name] ?? null;
    }

    /**
     * Register a lifecycle hook (boot, shutdown)
     */
    public function onLifecycle($event, callable $hook)
    {
        if (isset($this->lifecycleHooks[$event])) {
            $this->lifecycleHooks[$event][] = $hook;
        }
    }

    /**
     * Run lifecycle hooks
     */
    protected function runLifecycleHook($event)
    {
        if (!empty($this->lifecycleHooks[$event])) {
            foreach ($this->lifecycleHooks[$event] as $hook) {
                call_user_func($hook, $this);
            }
        }
    }

    // --- ADVANCED: Graceful shutdown (merged) ---
    // Already implemented above; removed deprecated duplicate.

    /**
     * Initialize the core with configuration and environment
     */
    public function __construct(array $config = [], array $env = [])
    {
        $this->config = $config;
        $this->env    = $env;

        // --- Logging: auto-initialize logger ---
        if (class_exists('Synchrenity\Logging\SynchrenityLogger')) {
            $logDir       = $config['log_dir'] ?? __DIR__ . '/../storage/logs';
            $this->logger = new \Synchrenity\Logging\SynchrenityLogger([
                'log_dir' => $logDir,
                'channel' => 'app',
                'level'   => $env['LOG_LEVEL']  ?? 'debug',
                'json'    => $env['LOG_JSON']   ?? true,
                'stdout'  => $env['LOG_STDOUT'] ?? false,
            ]);
            $this->log('info', 'SynchrenityCore initialized');
        }
        $this->setupErrorHandling();
        $this->auditTrail = new \Synchrenity\Audit\SynchrenityAuditTrail();

        // Automated audit injection for all major modules
        $modules = [
            'auth'           => ['\Synchrenity\Auth\SynchrenityAuth'],
            'queue'          => ['\Synchrenity\Queue\SynchrenityJobQueue'],
            'notifier'       => ['\Synchrenity\Notification\SynchrenityNotifier'],
            'media'          => ['\Synchrenity\Media\SynchrenityMediaManager'],
            'cache'          => ['\Synchrenity\Cache\SynchrenityCacheManager'],
            'rateLimiter'    => ['\Synchrenity\RateLimit\SynchrenityRateLimiter'],
            'tenant'         => ['\Synchrenity\Tenant\SynchrenityTenantManager'],
            'plugin'         => ['\Synchrenity\Plugin\SynchrenityPluginManager'],
            'i18n'           => ['\Synchrenity\I18n\SynchrenityI18nManager'],
            'websocket'      => ['\Synchrenity\WebSocket\SynchrenityWebSocketServer'],
            'validator'      => ['\Synchrenity\Validation\SynchrenityValidator'],
            'apiRateLimiter' => ['\Synchrenity\API\SynchrenityApiRateLimiter'],
            'oauth2Provider' => ['\Synchrenity\Auth\SynchrenityOAuth2Provider'],
        ];

        foreach ($modules as $prop => $classes) {
            foreach ($classes as $class) {
                if (class_exists($class)) {
                    $this->$prop = new $class();

                    if (method_exists($this->$prop, 'setAuditTrail')) {
                        $this->$prop->setAuditTrail($this->auditTrail);
                    }

                    // --- Logging: inject logger into modules if supported ---
                    if (property_exists($this->$prop, 'logger')) {
                        $this->$prop->logger = $this->logger;
                    } elseif (method_exists($this->$prop, 'setLogger')) {
                        $this->$prop->setLogger($this->logger);
                    }
                    $this->modules[$prop] = $this->$prop;
                }
            }
        }
        $this->log('info', 'All modules initialized');
        $this->runLifecycleHook('boot');
    }

    /**
     * Get the audit trail instance
     */
    public function audit()
    {
        return $this->auditTrail;
    }

    /**
     * Get the framework name for branding
     */
    public static function getFrameworkName()
    {
        return self::$frameworkName;
    }

    /**
     * Get config value by key (dot notation supported)
     */
    public function config($key, $default = null)
    {
        $segments = explode('.', $key);
        $value    = $this->config;

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Get environment variable
     */
    public function env($key, $default = null)
    {
        return $this->env[$key] ?? $default;
    }

    /**
     * Register a service provider
     */
    public function registerProvider($provider)
    {
        $this->providers[] = $provider;

        if (method_exists($provider, 'register')) {
            $provider->register($this);
        }
    }

    /**
     * Register an event listener
     */
    public function on($event, callable $listener)
    {
        $this->events[$event][] = $listener;
    }

    /**
     * Dispatch an event
     */
    public function dispatch($event, ...$args)
    {
        if (!empty($this->events[$event])) {
            foreach ($this->events[$event] as $listener) {
                call_user_func_array($listener, $args);
            }
        }
    }

    /**
     * Set a custom error handler
     */
    /**
     * Set the error handler instance (object, e.g. SynchrenityErrorHandler)
     */
    public function setErrorHandler($handler)
    {
        $this->errorHandler = $handler;

        if (is_object($handler) && method_exists($handler, 'phpErrorHandler') && method_exists($handler, 'phpExceptionHandler')) {
            set_error_handler([$handler, 'phpErrorHandler']);
            set_exception_handler([$handler, 'phpExceptionHandler']);
        }
    }

    /**
     * Handle an error using the error handler (always as array)
     */
    public function handleError($error)
    {
        if (!is_array($error)) {
            $error = [
                'type'    => 'error',
                'message' => is_string($error) ? $error : 'Unknown error',
                'context' => [],
                'code'    => 500,
            ];
        }

        if ($this->errorHandler && method_exists($this->errorHandler, 'handle')) {
            $this->errorHandler->handle($error);
        } else {
            // Fallback: log or echo
            $msg = $error['message'] ?? 'Unknown error';
            error_log('[Synchrenity Error] ' . $msg);
        }
    }

    /**
     * Setup default error handling
     */
    protected function setupErrorHandling()
    {
        // If SynchrenityErrorHandler exists, use it; else fallback
        if (class_exists('Synchrenity\\ErrorHandler\\SynchrenityErrorHandler')) {
            $handler = new \Synchrenity\ErrorHandler\SynchrenityErrorHandler(['logLevel' => 'error'], null, $this->logger);
            $this->setErrorHandler($handler);
        } else {
            // fallback: log errors to logger or error_log
            $this->setErrorHandler(null);
        }
    }

    /**
     * Handle the incoming HTTP request
     */
    public function handleRequest()
    {
        $this->log('info', 'Request received', [
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'uri'    => $_SERVER['REQUEST_URI']    ?? null,
            'ip'     => $_SERVER['REMOTE_ADDR']    ?? null,
        ]);
        // --- Event: request.received ---
        $this->dispatch('request.received');

        // --- Rate Limiting ---
        $rateLimiter = $this->rateLimiter;

        if ($rateLimiter) {
            $rateLimiter->setLimit('ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 100, 60); // 100 req/min per IP

            if (!$rateLimiter->check('ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'))) {
                $this->log('warning', 'Rate limit exceeded', ['ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
                $this->dispatch('rate.limit.exceeded');
                $response = new \Synchrenity\Http\SynchrenityResponse('Rate limit exceeded', 429);
                $response->send();

                return;
            }
        }

        // --- Event: before routing ---
        $this->dispatch('before.routing');

        // --- Routing (stub) ---
        $router = new \Synchrenity\Http\SynchrenityRouter();
        // Example route registration (should be done in bootstrap)
        $router->add('GET', '/', function ($req) {
            return new \Synchrenity\Http\SynchrenityResponse('Welcome to ' . \Synchrenity\SynchrenityCore::getFrameworkName() . '!');
        });
        $request  = new \Synchrenity\Http\SynchrenityRequest();
        $response = $router->dispatch($request);

        // --- Event: after routing ---
        $this->dispatch('after.routing', $response);


        // --- Send response ---
        // Skip sending response in test environment to avoid output during tests
        if ((getenv('APP_ENV') !== 'testing') && getenv('SYNCHRENITY_TESTING') !== '1') {
            $response->send();
            $this->log('info', 'Response sent', [
                'status' => $response->getStatusCode() ?? 200,
                'uri'    => $_SERVER['REQUEST_URI']    ?? null,
            ]);
        }

        // --- Event: response.sent ---
        $this->dispatch('response.sent', $response);
    }

    /**
     * Integrate CLI kernel
     */
    public function setCliKernel($kernel)
    {
        $this->cliKernel = $kernel;
    }

    /**
     * Run CLI command
     */
    public function runCli(array $args)
    {
        if ($this->cliKernel) {
            return $this->cliKernel->handle($args);
        }
        echo "CLI kernel not initialized.\n";

        return 1;
    }

    /**
     * Securely prompt for user input
     */
    public static function prompt($prompt, $secure = false)
    {
        if ($secure) {
            if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
                echo $prompt;

                return trim(fgets(STDIN));
            } else {
                echo $prompt;
                system('stty -echo');
                $input = trim(fgets(STDIN));
                system('stty echo');
                echo "\n";

                return $input;
            }
        } else {
            echo $prompt;

            return trim(fgets(STDIN));
        }
    }

    /**
     * Output colored text to the terminal
     */
    public static function colorEcho($text, $color = 'default')
    {
        $colors = [
            'default' => "\033[0m",
            'red'     => "\033[31m",
            'green'   => "\033[32m",
            'yellow'  => "\033[33m",
            'blue'    => "\033[34m",
            'magenta' => "\033[35m",
            'cyan'    => "\033[36m",
        ];
        $colorCode = $colors[$color] ?? $colors['default'];
        echo $colorCode . $text . $colors['default'];
    }

}
