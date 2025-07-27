<?php
// lib/SynchrenityCore.php
namespace Synchrenity;

/**
 * SynchrenityCore: The main framework class
 * - Manages configuration, environment, request/response, error handling, service providers, events, and CLI integration.
 * - Designed for security, scalability, and developer experience.
 */
class SynchrenityCore
{
    /**
     * Stores application configuration
     * @var array
     */
    protected $config;

    /**
     * Stores loaded environment variables
     * @var array
     */
    protected $env;

    /**
     * Registered service providers
     * @var array
     */
    protected $providers = [];

    /**
     * Registered event listeners
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
    protected $errorHandler;

    /**
     * CLI kernel instance
     */
    protected $cliKernel;

    /**
     * Audit trail instance
     */
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
    protected $modules = [];
    protected $lifecycleHooks = [ 'boot' => [], 'shutdown' => [] ];

    /**
     * Register a module dynamically
     */
    public function registerModule($name, $instance) {
        $this->modules[$name] = $instance;
        if (method_exists($instance, 'setAuditTrail')) {
            $instance->setAuditTrail($this->auditTrail);
        }
        $this->$name = $instance;
    }

    /**
     * Get a registered module
     */
    public function getModule($name) {
        return $this->modules[$name] ?? null;
    }

    /**
     * Register a lifecycle hook (boot, shutdown)
     */
    public function onLifecycle($event, callable $hook) {
        if (isset($this->lifecycleHooks[$event])) {
            $this->lifecycleHooks[$event][] = $hook;
        }
    }

    /**
     * Run lifecycle hooks
     */
    protected function runLifecycleHook($event) {
        if (!empty($this->lifecycleHooks[$event])) {
            foreach ($this->lifecycleHooks[$event] as $hook) {
                call_user_func($hook, $this);
            }
        }
    }

    /**
     * Shutdown the framework (run shutdown hooks)
     */
    public function shutdown() {
        $this->runLifecycleHook('shutdown');
    }

    /**
     * Initialize the core with configuration and environment
     */
    public function __construct(array $config = [], array $env = [])
    {
        $this->config = $config;
        $this->env = $env;
        $this->setupErrorHandling();
        $this->auditTrail = new \Synchrenity\Audit\SynchrenityAuditTrail();

        // Automated audit injection for all major modules
        $modules = [
            'auth' => ['\Synchrenity\Auth\SynchrenityAuth'],
            'queue' => ['\Synchrenity\Queue\SynchrenityJobQueue'],
            'notifier' => ['\Synchrenity\Notification\SynchrenityNotifier'],
            'media' => ['\Synchrenity\Media\SynchrenityMediaManager'],
            'cache' => ['\Synchrenity\Cache\SynchrenityCacheManager'],
            'rateLimiter' => ['\Synchrenity\RateLimit\SynchrenityRateLimiter'],
            'tenant' => ['\Synchrenity\Tenant\SynchrenityTenantManager'],
            'plugin' => ['\Synchrenity\Plugin\SynchrenityPluginManager'],
            'i18n' => ['\Synchrenity\I18n\SynchrenityI18nManager'],
            'websocket' => ['\Synchrenity\WebSocket\SynchrenityWebSocketServer'],
            'validator' => ['\Synchrenity\Validation\SynchrenityValidator'],
            'apiRateLimiter' => ['\Synchrenity\API\SynchrenityApiRateLimiter'],
            'oauth2Provider' => ['\Synchrenity\Auth\SynchrenityOAuth2Provider']
        ];
        foreach ($modules as $prop => $classes) {
            foreach ($classes as $class) {
                if (class_exists($class)) {
                    $this->$prop = new $class();
                    if (method_exists($this->$prop, 'setAuditTrail')) {
                        $this->$prop->setAuditTrail($this->auditTrail);
                    }
                    $this->modules[$prop] = $this->$prop;
                }
            }
        }
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
        $value = $this->config;
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
    public function setErrorHandler(callable $handler)
    {
        $this->errorHandler = $handler;
        set_error_handler($handler);
        set_exception_handler($handler);
    }

    /**
     * Setup default error handling
     */
    protected function setupErrorHandling()
    {
        $this->setErrorHandler(function($e) {
            $msg = $e instanceof \Throwable ? $e->getMessage() : $e;
            echo "[Synchrenity Error] " . $msg . "\n";
        });
    }

    /**
     * Handle the incoming HTTP request
     */
    public function handleRequest()
    {
        // --- Event: request.received ---
        $this->dispatch('request.received');

        // --- Rate Limiting ---
        $rateLimiter = new \Synchrenity\Http\SynchrenityRateLimiter();
        $rateLimiter->setLimit('ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 100, 60); // 100 req/min per IP
        if (!$rateLimiter->check('ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'))) {
            $this->dispatch('rate.limit.exceeded');
            $response = new \Synchrenity\Http\SynchrenityResponse('Rate limit exceeded', 429);
            $response->send();
            return;
        }

        // --- Event: before routing ---
        $this->dispatch('before.routing');

        // --- Routing (stub) ---
        $router = new \Synchrenity\Http\SynchrenityRouter();
        // Example route registration (should be done in bootstrap)
        $router->add('GET', '/', function($req) {
            return new \Synchrenity\Http\SynchrenityResponse('Welcome to ' . \Synchrenity\SynchrenityCore::getFrameworkName() . '!');
        });
        $request = new \Synchrenity\Http\SynchrenityRequest();
        $response = $router->dispatch($request);

        // --- Event: after routing ---
        $this->dispatch('after.routing', $response);

        // --- Send response ---
        $response->send();

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
