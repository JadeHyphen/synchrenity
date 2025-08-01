<?php

declare(strict_types=1);

namespace Synchrenity;

use Synchrenity\Support\SynchrenityEventDispatcher;
use Synchrenity\Support\SynchrenityMiddlewareManager;
use Synchrenity\Security\SynchrenitySecurityManager;
use Synchrenity\Support\SynchrenityLogger;
use Synchrenity\Support\SynchrenityServiceContainer;
use Synchrenity\Support\SynchrenityConfigManager;
use Synchrenity\Support\{
    SynchrenityCoreServiceProvider,
    SynchrenitySecurityServiceProvider,
    SynchrenityHttpServiceProvider
};
use Synchrenity\API\SynchrenityApiRateLimiter;
use Synchrenity\Http\SynchrenityRequest;
use Synchrenity\Http\SynchrenityResponse;
use Synchrenity\Http\SynchrenityRouter;

/**
 * SynchrenityApplication: Main application orchestrator
 * Handles all application logic that was previously in public/index.php
 */
class SynchrenityApplication
{
    protected SynchrenityCore $core;
    protected SynchrenityEventDispatcher $eventDispatcher;
    protected SynchrenityMiddlewareManager $middlewareManager;
    protected SynchrenitySecurityManager $securityManager;
    protected SynchrenityServiceContainer $container;
    protected SynchrenityConfigManager $configManager;
    protected ?SynchrenityApiRateLimiter $rateLimiter = null;
    protected array $config = [];
    protected bool $isBooted = false;
    protected array $errorHandlers = [];
    protected array $serviceProviders = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->setupErrorHandling();
        $this->initializeContainer();
        $this->initializeConfig();
        $this->registerServiceProviders();
        $this->initializeCore();
        $this->setupSecurity();
        $this->setupMiddleware();
        $this->setupRateLimiting();
    }

    /**
     * Initialize the service container
     */
    protected function initializeContainer(): void
    {
        $this->container = new SynchrenityServiceContainer();
        $GLOBALS['synchrenityContainer'] = $this->container;
    }

    /**
     * Initialize configuration management
     */
    protected function initializeConfig(): void
    {
        $this->configManager = new SynchrenityConfigManager();
        $this->configManager->merge($this->config);
        
        // Register config in container
        $this->container->instance('config', $this->configManager);
    }

    /**
     * Register core service providers
     */
    protected function registerServiceProviders(): void
    {
        $providers = [
            new SynchrenityCoreServiceProvider($this->container),
            new SynchrenitySecurityServiceProvider($this->container),
            new SynchrenityHttpServiceProvider($this->container),
        ];

        foreach ($providers as $provider) {
            $provider->register();
            $this->serviceProviders[] = $provider;
        }
    }

    /**
     * Setup comprehensive error handling
     */
    protected function setupErrorHandling(): void
    {
        // Set error reporting based on environment
        $env = $this->config['env'] ?? 'production';
        
        if ($env === 'development' || $env === 'dev') {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', '0');
            ini_set('log_errors', '1');
            error_reporting(E_ALL);
        }

        // Global exception handler
        set_exception_handler(function(\Throwable $e) {
            $this->handleException($e);
        });

        // Global error handler
        set_error_handler(function($severity, $message, $file, $line) {
            $this->handleError($severity, $message, $file, $line);
        });
    }

    /**
     * Initialize the core framework
     */
    protected function initializeCore(): void
    {
        $this->core = new SynchrenityCore($this->configManager->all());
        $this->eventDispatcher = new SynchrenityEventDispatcher();
        
        // Register core in container
        $this->container->instance('core', $this->core);
        $this->container->instance('events', $this->eventDispatcher);
        
        // Set up core components
        if (method_exists($this->core, 'setEventDispatcher')) {
            $this->core->setEventDispatcher($this->eventDispatcher);
        }
    }

    /**
     * Setup security systems
     */
    protected function setupSecurity(): void
    {
        $this->securityManager = $this->container->get('security');
        
        // Set security headers
        $this->setSecurityHeaders();
        
        if (method_exists($this->core, 'setSecurityManager')) {
            $this->core->setSecurityManager($this->securityManager);
        }
    }

    /**
     * Setup middleware system
     */
    protected function setupMiddleware(): void
    {
        $this->middlewareManager = $this->container->get('middleware');
        
        // Integrate systems
        $this->middlewareManager->attachToDispatcher($this->eventDispatcher);
        $this->securityManager->attachMiddlewareManager($this->middlewareManager);

        // Register default security middleware
        $this->registerSecurityMiddleware();
        
        if (method_exists($this->core, 'setMiddlewareManager')) {
            $this->core->setMiddlewareManager($this->middlewareManager);
        }
    }

    /**
     * Setup rate limiting
     */
    protected function setupRateLimiting(): void
    {
        // Load rate limiting configuration
        $apiRateLimitsConfig = $this->loadConfig('api_rate_limits');
        
        if ($apiRateLimitsConfig) {
            $this->rateLimiter = new SynchrenityApiRateLimiter(
                $apiRateLimitsConfig,
                [],
                function($user, $role, $endpoint) use ($apiRateLimitsConfig) {
                    return $apiRateLimitsConfig[$endpoint][$role] ?? null;
                }
            );

            // Set up rate limiter with core if available
            if (isset($this->core) && method_exists($this->core, 'audit')) {
                $this->rateLimiter->setAuditTrail($this->core->audit());
            }

            // Register rate limiting middleware
            $this->registerRateLimitingMiddleware();
        }
    }

    /**
     * Set security headers
     */
    protected function setSecurityHeaders(): void
    {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Content-Security-Policy: default-src \'self\';');
        header('X-XSS-Protection: 1; mode=block');
        
        // Only set HSTS in production
        if (($this->config['env'] ?? 'production') === 'production' && 
            (!empty($_SERVER['HTTPS']) || $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Register security middleware
     */
    protected function registerSecurityMiddleware(): void
    {
        $this->middlewareManager->registerSecurityHook(function($payload, $context) {
            // CSRF protection
            if (isset($payload['csrf_token']) && 
                !$this->securityManager->protectCSRF($payload['csrf_token'])) {
                $this->sendSecurityResponse('CSRF validation failed', 403);
                return false;
            }

            // XSS protection
            if (isset($payload['input'])) {
                $payload['input'] = $this->securityManager->protectXSS($payload['input']);
            }

            return true;
        });
    }

    /**
     * Register rate limiting middleware
     */
    protected function registerRateLimitingMiddleware(): void
    {
        if (!$this->rateLimiter) {
            return;
        }

        $this->middlewareManager->registerGlobal(function($payload, $context) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user = $context['user'] ?? $ip;
            $role = $context['role'] ?? 'guest';
            $endpoint = $_SERVER['REQUEST_URI'] ?? 'default';
            
            $allowed = $this->rateLimiter->check($user, $role, $endpoint);
            
            // Add rate limit headers
            $meta = $this->rateLimiter->getContext('meta', []);
            header('X-RateLimit-Limit: ' . ($meta['limit'] ?? ''));
            header('X-RateLimit-Remaining: ' . max(0, ($meta['limit'] ?? 0) - ($meta['count'] ?? 0)));
            header('X-RateLimit-Reset: ' . ($meta['window'] ?? 60));

            if (!$allowed) {
                $this->sendSecurityResponse('Rate limit exceeded', 429, [
                    'X-RateLimit-Limit' => (string)($meta['limit'] ?? ''),
                    'X-RateLimit-Remaining' => '0',
                    'X-RateLimit-Reset' => (string)($meta['window'] ?? 60)
                ]);
                return false;
            }

            return true;
        }, 1);
    }

    /**
     * Boot the application
     */
    public function boot(): void
    {
        if ($this->isBooted) {
            return;
        }

        // Boot service providers
        foreach ($this->serviceProviders as $provider) {
            if (method_exists($provider, 'boot')) {
                $provider->boot();
            }
        }

        // Boot container
        $this->container->boot();

        // Register lifecycle hooks
        $this->registerLifecycleHooks();
        
        // Load additional modules
        $this->loadModules();
        
        // Mark as booted
        $this->isBooted = true;
        
        // Trigger boot event
        $this->eventDispatcher->dispatch('application.booted', $this);
    }

    /**
     * Handle HTTP request
     */
    public function handleRequest(): void
    {
        if (!$this->isBooted) {
            $this->boot();
        }

        try {
            $this->logRequest();
            
            // Dispatch request received event
            $this->eventDispatcher->dispatch('request.received');

            // Run middleware pipeline first
            $middlewareResult = $this->middlewareManager->runGlobal([], [
                'user' => $this->getCurrentUser(),
                'role' => $this->getCurrentUserRole()
            ]);

            if (!$middlewareResult) {
                return; // Middleware handled the response (e.g., rate limiting)
            }

            // Handle the actual request
            $this->dispatchRequest();
            
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Dispatch the request to the router
     */
    protected function dispatchRequest(): void
    {
        $router = new SynchrenityRouter();
        
        // Register default routes
        $this->registerDefaultRoutes($router);
        
        // Dispatch request
        $request = new SynchrenityRequest();
        $response = $router->dispatch($request);

        // Send response (skip in testing environment)
        if (!$this->isTestingEnvironment()) {
            $response->send();
            $this->logResponse($response);
        }

        // Dispatch response sent event
        $this->eventDispatcher->dispatch('response.sent', $response);
    }

    /**
     * Register default routes
     */
    protected function registerDefaultRoutes(SynchrenityRouter $router): void
    {
        // Welcome route
        $router->add('GET', '/', function($req) {
            return new SynchrenityResponse('Welcome to ' . SynchrenityCore::getFrameworkName() . '!');
        });

        // Health check route
        $router->add('GET', '/health', function($req) {
            return new SynchrenityResponse(json_encode($this->core->health()), 200, [
                'Content-Type' => 'application/json'
            ]);
        });

        // Debug routes (only in development)
        if ($this->isDevelopmentEnvironment()) {
            $this->registerDebugRoutes($router);
        }
    }

    /**
     * Register debug routes for development
     */
    protected function registerDebugRoutes(SynchrenityRouter $router): void
    {
        $router->add('GET', '/__debug/config', function($req) {
            if (!$this->isDevelopmentEnvironment()) {
                return new SynchrenityResponse('Not found', 404);
            }
            
            return new SynchrenityResponse(json_encode($this->config, JSON_PRETTY_PRINT), 200, [
                'Content-Type' => 'application/json'
            ]);
        });

        $router->add('GET', '/__debug/metrics', function($req) {
            if (!$this->isDevelopmentEnvironment()) {
                return new SynchrenityResponse('Not found', 404);
            }
            
            $metrics = [
                'core' => $this->core->getMetrics(),
                'rate_limiter' => $this->rateLimiter ? $this->rateLimiter->getMetrics() : null
            ];
            
            return new SynchrenityResponse(json_encode($metrics, JSON_PRETTY_PRINT), 200, [
                'Content-Type' => 'application/json'
            ]);
        });
    }

    /**
     * Load configuration file
     */
    protected function loadConfig(string $name): ?array
    {
        $configFile = __DIR__ . "/../config/{$name}.php";
        if (file_exists($configFile)) {
            $config = require $configFile;
            return is_array($config) ? $config : null;
        }
        return null;
    }

    /**
     * Register lifecycle hooks
     */
    protected function registerLifecycleHooks(): void
    {
        $this->core->onLifecycle('boot', function($core) {
            if (method_exists($core, 'audit')) {
                $core->audit()->log('system.boot', [
                    'timestamp' => time(),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]);
            }
        });

        $this->core->onLifecycle('shutdown', function($core) {
            if (method_exists($core, 'audit')) {
                $core->audit()->log('system.shutdown', [
                    'timestamp' => time(),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }
        });
    }

    /**
     * Load additional modules
     */
    protected function loadModules(): void
    {
        // Register logger if available
        if (class_exists('Synchrenity\Support\SynchrenityLogger')) {
            $logger = new SynchrenityLogger();
            $this->core->registerModule('logger', $logger);
        }
    }

    /**
     * Handle exceptions
     */
    protected function handleException(\Throwable $e): void
    {
        // Log the exception
        error_log('[Synchrenity Exception] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        
        // Audit if available
        if (isset($this->core) && method_exists($this->core, 'audit')) {
            $this->core->audit()->log('error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        // Send appropriate response
        if (!$this->isTestingEnvironment() && !headers_sent()) {
            http_response_code(500);
            
            if ($this->isDevelopmentEnvironment()) {
                echo "<h1>Internal Server Error</h1>";
                echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
                echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
            } else {
                echo "<h1>Internal Server Error</h1>";
            }
        }
    }

    /**
     * Handle PHP errors
     */
    protected function handleError(int $severity, string $message, string $file, int $line): void
    {
        // Convert to exception for consistent handling
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Send security response
     */
    protected function sendSecurityResponse(string $message, int $code, array $headers = []): void
    {
        if (!headers_sent()) {
            http_response_code($code);
            
            foreach ($headers as $name => $value) {
                header("{$name}: {$value}");
            }
            
            if (!$this->isTestingEnvironment()) {
                echo "<h1>{$message}</h1>";
            }
        }
    }

    /**
     * Log incoming request
     */
    protected function logRequest(): void
    {
        if (!empty($_SERVER['REQUEST_METHOD'])) {
            error_log('[Synchrenity] ' . 
                $_SERVER['REQUEST_METHOD'] . ' ' . 
                ($_SERVER['REQUEST_URI'] ?? '') . ' from ' . 
                ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        }
    }

    /**
     * Log response
     */
    protected function logResponse(SynchrenityResponse $response): void
    {
        if (method_exists($this->core, 'audit')) {
            $this->core->audit()->log('request.completed', [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'status' => $response->getStatus(),
                'rate_metrics' => $this->rateLimiter ? $this->rateLimiter->getMetrics() : null
            ]);
        }
    }

    /**
     * Get current user (stub - should be implemented based on auth system)
     */
    protected function getCurrentUser(): ?string
    {
        // This would typically get the user from session/token
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Get current user role (stub)
     */
    protected function getCurrentUserRole(): string
    {
        // This would typically get the role from auth system
        return 'guest';
    }

    /**
     * Check if in development environment
     */
    protected function isDevelopmentEnvironment(): bool
    {
        $env = $this->config['env'] ?? getenv('APP_ENV') ?? 'production';
        return in_array($env, ['development', 'dev', 'local'], true);
    }

    /**
     * Check if in testing environment
     */
    protected function isTestingEnvironment(): bool
    {
        return (getenv('APP_ENV') === 'testing') || (getenv('SYNCHRENITY_TESTING') === '1');
    }

    /**
     * Shutdown the application
     */
    public function shutdown(): void
    {
        if (isset($this->core)) {
            $this->core->shutdown();
        }
    }

    /**
     * Get the core instance
     */
    public function getCore(): SynchrenityCore
    {
        return $this->core;
    }

    /**
     * Get configuration value
     */
    public function getConfig(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->configManager->all();
        }
        
        return $this->configManager->get($key, $default);
    }

    /**
     * Get the service container
     */
    public function getContainer(): SynchrenityServiceContainer
    {
        return $this->container;
    }

    /**
     * Get service from container
     */
    public function make(string $service)
    {
        return $this->container->get($service);
    }

    /**
     * Register a service in the container
     */
    public function bind(string $name, callable $factory): void
    {
        $this->container->bind($name, $factory);
    }

    /**
     * Register a singleton in the container
     */
    public function singleton(string $name, callable $factory): void
    {
        $this->container->singleton($name, $factory);
    }
}