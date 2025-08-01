<?php

declare(strict_types=1);

namespace Synchrenity\Security;

/**
 * SynchrenitySecurityManager: Centralized, robust, and extensible security system
 * Features: authentication, authorization, encryption, validation, rate limiting, audit logging, middleware integration
 */
class SynchrenitySecurityManager
{
    // Authentication modules
    protected array $authModules = [];
    protected $userProvider;
    protected $sessionManager;
    protected $tokenManager;
    protected $mfaManager;
    protected $passwordlessManager;

    // Authorization modules
    protected $rbac;
    protected $abac;
    protected array $policies = [];
    protected array $guards   = [];

    // Encryption/hashing
    protected ?string $encryptionKey = null;
    protected $hasher;

    // Validation/sanitization
    protected array $validators = [];
    protected array $sanitizers = [];

    // Protection modules
    protected $csrfProtector;
    protected $xssProtector;
    protected $sqliProtector;
    protected $rateLimiter;
    protected $bruteForceProtector;

    // Audit logging
    protected $auditLogger;

    // Middleware integration
    protected $middlewareManager;

    /**
     * Security event hooks (onLogin, onLogout, onAuthFail, onRateLimit, etc.)
     */
    protected array $eventHooks = [];

    /**
     * Security monitoring and alerts (stub)
     */
    protected array $alerts = [];

    /**
     * Custom error handling for security operations
     */
    protected ?string $lastError = null;

    public function __construct(array $config = [])
    {
        // Initialize modules from config or defaults
        $this->encryptionKey = $config['encryption_key'] ?? bin2hex(random_bytes(32));
        $this->hasher        = $config['hasher']         ?? 'bcrypt';
        // ...initialize other modules as needed...
    }

    /**
     * Register authentication module (e.g., password, token, OAuth, MFA)
     */
    public function registerAuthModule(string $name, $module): void
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('Authentication module name cannot be empty');
        }
        $this->authModules[$name] = $module;
    }

    /**
     * Authenticate user (delegates to registered modules)
     */
    public function authenticate($credentials, string $type = 'password'): bool
    {
        if (!isset($this->authModules[$type])) {
            $this->lastError = "Authentication module '$type' not found";
            return false;
        }

        try {
            return $this->authModules[$type]->authenticate($credentials);
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Authorize user (RBAC, ABAC, policies, guards)
     */
    public function authorize($user, string $action, $resource = null, array $context = []): bool
    {
        if (empty($action)) {
            $this->lastError = 'Action cannot be empty';
            return false;
        }

        try {
            // RBAC check
            if ($this->rbac && !$this->rbac->can($user, $action, $resource)) {
                return false;
            }

            // ABAC check
            if ($this->abac && !$this->abac->can($user, $action, $resource, $context)) {
                return false;
            }

            // Policy check
            foreach ($this->policies as $policy) {
                if (!$policy->check($user, $action, $resource, $context)) {
                    return false;
                }
            }

            // Guard check
            foreach ($this->guards as $guard) {
                if (!$guard->check($user, $action, $resource, $context)) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Encrypt data with proper IV generation
     */
    public function encrypt($data)
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Data to encrypt cannot be empty');
        }
        
        $iv = random_bytes(16); // Generate random IV for each encryption
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        
        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed');
        }
        
        // Prepend IV to encrypted data
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt data with proper IV extraction
     */
    public function decrypt($data)
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Data to decrypt cannot be empty');
        }
        
        $data = base64_decode($data);
        if ($data === false || strlen($data) < 16) {
            throw new \InvalidArgumentException('Invalid encrypted data format');
        }
        
        $iv = substr($data, 0, 16); // Extract IV from beginning
        $encrypted = substr($data, 16); // Get encrypted portion
        
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        
        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed');
        }
        
        return $decrypted;
    }

    /**
     * Hash data (passwords, tokens)
     */
    public function hash($data)
    {
        if ($this->hasher === 'bcrypt') {
            return password_hash($data, PASSWORD_BCRYPT);
        }

        // Add more hashers as needed
        return hash('sha256', $data);
    }
    public function verifyHash($data, $hash)
    {
        if ($this->hasher === 'bcrypt') {
            return password_verify($data, $hash);
        }

        return hash('sha256', $data) === $hash;
    }

    /**
     * Validate and sanitize input with security defaults
     */
    public function validate($type, $value)
    {
        if (isset($this->validators[$type])) {
            return $this->validators[$type]->validate($value);
        }
        
        // Default validation based on type
        switch ($type) {
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) !== false;
            case 'int':
                return filter_var($value, FILTER_VALIDATE_INT) !== false;
            case 'float':
                return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
            case 'ip':
                return filter_var($value, FILTER_VALIDATE_IP) !== false;
            default:
                return is_string($value) && strlen($value) <= 10000; // Basic length check
        }
    }
    
    public function sanitize($type, $value)
    {
        if (isset($this->sanitizers[$type])) {
            return $this->sanitizers[$type]->sanitize($value);
        }
        
        // Default sanitization with security focus
        if (!is_string($value)) {
            return $value;
        }
        
        switch ($type) {
            case 'html':
                return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            case 'sql':
                return addslashes($value);
            case 'filename':
                return preg_replace('/[^a-zA-Z0-9_.-]/', '', basename($value));
            case 'path':
                // Prevent directory traversal
                $value = str_replace(['../', '.\\', '..\\'], '', $value);
                return preg_replace('/[^a-zA-Z0-9\/_.-]/', '', $value);
            default:
                return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    /**
     * CSRF/XSS/SQLi protection hooks with improved security
     */
    public function protectCSRF($token)
    {
        if (!$this->csrfProtector) {
            error_log('[Security Warning] CSRF protector not configured');
            return false; // Fail secure
        }
        return $this->csrfProtector->validate($token);
    }
    
    public function protectXSS($input)
    {
        if ($this->xssProtector) {
            return $this->xssProtector->sanitize($input);
        }
        
        // Enhanced XSS protection
        if (!is_string($input)) {
            return $input;
        }
        
        // Remove potentially dangerous tags and attributes
        $input = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $input);
        $input = preg_replace('/on\w+\s*=\s*["\'].*?["\']/i', '', $input);
        $input = preg_replace('/javascript:/i', '', $input);
        $input = preg_replace('/vbscript:/i', '', $input);
        
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    public function protectSQLi($input)
    {
        if ($this->sqliProtector) {
            return $this->sqliProtector->sanitize($input);
        }
        
        // Enhanced SQL injection protection
        if (!is_string($input)) {
            return $input;
        }
        
        // Remove common SQL injection patterns
        $patterns = [
            '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC)\b)/i',
            '/(\b(UNION|OR|AND)\s+\d+\s*=\s*\d+)/i',
            '/(--|\#|\/\*|\*\/)/i',
            '/(\b(SCRIPT|JAVASCRIPT|VBSCRIPT)\b)/i'
        ];
        
        foreach ($patterns as $pattern) {
            $input = preg_replace($pattern, '', $input);
        }
        
        return addslashes(trim($input));
    }

    /**
     * Rate limiting and brute-force protection
     */
    public function rateLimit($key, $limit = 10, $window = 60)
    {
        return $this->rateLimiter ? $this->rateLimiter->check($key, $limit, $window) : true;
    }
    public function protectBruteForce($user)
    {
        return $this->bruteForceProtector ? $this->bruteForceProtector->check($user) : true;
    }

    /**
     * Audit logging
     */
    public function audit($event, $details = [])
    {
        if ($this->auditLogger) {
            $this->auditLogger->log($event, $details);
        }
    }

    /**
     * Integrate with middleware manager
     */
    public function attachMiddlewareManager($manager)
    {
        $this->middlewareManager = $manager;
    }

    /**
     * Core authentication logic: password, token, OAuth, MFA, passwordless
     */
    public function defaultPasswordAuth($credentials)
    {
        // Example: $credentials = ['username' => '', 'password' => '']
        $user = $this->userProvider ? $this->userProvider->findByUsername($credentials['username']) : null;

        if (!$user || !$this->verifyHash($credentials['password'], $user['password_hash'])) {
            return false;
        }
        $this->sessionManager && $this->sessionManager->start($user);
        $this->audit('login', ['user' => $user['id'] ?? null]);

        return $user;
    }
    public function defaultTokenAuth($token)
    {
        return $this->tokenManager ? $this->tokenManager->validate($token) : false;
    }
    public function defaultMfaAuth($user, $code)
    {
        return $this->mfaManager ? $this->mfaManager->verify($user, $code) : false;
    }
    public function defaultPasswordlessAuth($identifier)
    {
        return $this->passwordlessManager ? $this->passwordlessManager->authenticate($identifier) : false;
    }

    /**
     * Core RBAC/ABAC logic
     */
    public function setRbac($rbac)
    {
        $this->rbac = $rbac;
    }
    public function setAbac($abac)
    {
        $this->abac = $abac;
    }
    public function addPolicy($policy)
    {
        $this->policies[] = $policy;
    }
    public function addGuard($guard)
    {
        $this->guards[] = $guard;
    }

    /**
     * Core encryption/hashing logic
     */
    public function setEncryptionKey($key)
    {
        $this->encryptionKey = $key;
    }
    public function setHasher($hasher)
    {
        $this->hasher = $hasher;
    }

    /**
     * Core validation/sanitization logic
     */
    public function addValidator($type, $validator)
    {
        $this->validators[$type] = $validator;
    }
    public function addSanitizer($type, $sanitizer)
    {
        $this->sanitizers[$type] = $sanitizer;
    }

    /**
     * Core protection logic
     */
    public function setCsrfProtector($protector)
    {
        $this->csrfProtector = $protector;
    }
    public function setXssProtector($protector)
    {
        $this->xssProtector = $protector;
    }
    public function setSqliProtector($protector)
    {
        $this->sqliProtector = $protector;
    }
    public function setRateLimiter($limiter)
    {
        $this->rateLimiter = $limiter;
    }
    public function setBruteForceProtector($protector)
    {
        $this->bruteForceProtector = $protector;
    }

    /**
     * Core audit logger logic
     */
    public function setAuditLogger($logger)
    {
        $this->auditLogger = $logger;
    }

    /**
     * Core session/token management
     */
    public function setSessionManager($manager)
    {
        $this->sessionManager = $manager;
    }
    public function setTokenManager($manager)
    {
        $this->tokenManager = $manager;
    }
    public function setUserProvider($provider)
    {
        $this->userProvider = $provider;
    }
    public function setMfaManager($manager)
    {
        $this->mfaManager = $manager;
    }
    public function setPasswordlessManager($manager)
    {
        $this->passwordlessManager = $manager;
    }

    /**
     * Security middleware integration
     */
    public function enforceMiddleware($type, $payload = [], $context = [])
    {
        return $this->middlewareManager ? $this->middlewareManager->handle($type, null, $payload, $context) : true;
    }

    /**
     * Register an event hook
     */
    public function registerEventHook($event, callable $hook)
    {
        if (!isset($this->eventHooks[$event])) {
            $this->eventHooks[$event] = [];
        }
        $this->eventHooks[$event][] = $hook;
    }

    /**
     * Trigger an event hook
     */
    protected function triggerEventHook($event, $details = [])
    {
        if (!isset($this->eventHooks[$event]) || !is_array($this->eventHooks[$event])) {
            return;
        }

        foreach ($this->eventHooks[$event] as $hook) {
            if (is_callable($hook)) {
                try {
                    call_user_func($hook, $details);
                } catch (\Throwable $e) {
                    // Optionally log or handle hook errors
                }
            }
        }
    }

    /**
     * Security alert (stub)
     */
    public function alert($type, $details = [])
    {
        $this->alerts[] = ['type' => $type, 'details' => $details, 'timestamp' => time()];
        // Extend: send to external monitoring, email, etc.
    }
    public function getAlerts($type = null)
    {
        return $type ? array_filter($this->alerts, fn ($a) => $a['type'] === $type) : $this->alerts;
    }

    /**
     * Set custom error
     */
    public function setError($error)
    {
        $this->lastError = $error;
    }
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Security integration tests (stub)
     */
    public function testSecurity($testCase)
    {
        // Example: $testCase = ['type' => 'auth', 'input' => ...]
        // Extend: run test logic, return result
        return true;
    }

    /**
     * Security documentation helper
     */
    public function getDocumentation()
    {
        return [
            'features' => [
                'authentication', 'authorization', 'encryption', 'validation', 'rate limiting',
                'audit logging', 'middleware integration', 'event hooks', 'monitoring', 'error handling', 'testing',
            ],
            'usage' => 'See SynchrenitySecurityManager.php for API and integration points.',
        ];
    }
}
