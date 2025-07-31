<?php

declare(strict_types=1);

namespace Synchrenity\Security;

class SynchrenityCsrf
{
    protected string $sessionKey        = '_synchrenity_csrf';
    protected bool $bindIp            = false;
    protected bool $bindUa            = false;
    protected array $scopes            = [];
    protected bool $replayProtection  = true;
    protected array $usedTokens        = [];
    protected string $formTokensKey     = '_synchrenity_csrf_forms';
    protected bool $multiTokenPerForm = false;
    protected array $tokenMeta         = [];
    protected string $hashAlgo          = 'sha256';
    protected ?string $userKey           = null;
    protected array $rotationHooks     = [];
    protected array $log               = [];
    protected bool $complianceMode    = false;
    protected $externalVerifier  = null;
    protected int $tokenTtl          = 1800; // 30 min
    protected string $backend           = 'session';
    protected array $eventHooks        = [ 'generate' => [], 'validate' => [], 'rotate' => [], 'fail' => [] ];
    protected array $auditTrail        = [];
    protected ?string $lastError         = null;
    protected string $oneTimeTokensKey  = '_synchrenity_csrf_ott';
    protected int $tokenUsageLimit   = 1;
    protected array $tokenUsageCounts  = [];
    protected array $revokedTokens     = [];
    protected array $stats             = [ 'generates' => 0, 'validates' => 0, 'fails' => 0, 'rotates' => 0 ];
    protected array $arrayStore        = [];
    protected $policy            = null;

    // Event hooks
    public function on(string $event, callable $cb): void
    {
        if (isset($this->eventHooks[$event])) {
            $this->eventHooks[$event][] = $cb;
        }
    }
    protected function trigger(string $event, ...$args): void
    {
        if (isset($this->eventHooks[$event])) {
            foreach ($this->eventHooks[$event] as $cb) {
                call_user_func_array($cb, $args);
            }
        }
    }

    // Audit trail
    public function auditTrail()
    {
        return $this->auditTrail;
    }

    // Backend abstraction
    protected function &store()
    {
        if ($this->backend === 'session') {
            return $_SESSION;
        }

        if ($this->backend === 'array') {
            return $this->arrayStore;
        }

        // Could add: redis, db, etc.
        throw new \Exception('Unsupported CSRF backend');
    }

    // Generate a global token
    public function generateToken()
    {
        $store = &$this->store();

        if (empty($store[$this->sessionKey]) || $this->isExpired($store[$this->sessionKey.'_time'] ?? 0)) {
            $token = bin2hex(random_bytes(32));

            if (!$this->isStrong($token)) {
                $this->lastError = 'Token entropy too low';
                $this->stats['fails']++;

                return null;
            }
            $store[$this->sessionKey]         = $token;
            $store[$this->sessionKey.'_time'] = time();
            $this->trigger('generate', $store[$this->sessionKey]);
            $this->auditTrail[] = [ 'event' => 'generate', 'token' => $store[$this->sessionKey], 'time' => time() ];
            $this->stats['generates']++;
        }

        return $store[$this->sessionKey];
    }
    // One-time token generation
    public function generateOneTimeToken($scope = null, $user = null)
    {
        $store = &$this->store();
        $token = bin2hex(random_bytes(32));
        $meta  = [ 'time' => time(), 'used' => 0 ];

        if ($this->bindIp) {
            $meta['ip'] = $_SERVER['REMOTE_ADDR'] ?? null;
        }

        if ($this->bindUa) {
            $meta['ua'] = $_SERVER['HTTP_USER_AGENT'] ?? null;
        }

        if ($scope) {
            $meta['scope'] = $scope;
        }

        if ($user || $this->userKey) {
            $meta['user'] = $user ?: $this->userKey;
        }
        $store[$this->oneTimeTokensKey][$token] = $meta;
        $this->tokenMeta[$token]                = $meta;
        $this->stats['generates']++;

        return $token;
    }

    public function validateOneTimeToken($token, $scope = null)
    {
        $store = &$this->store();
        $meta  = $store[$this->oneTimeTokensKey][$token] ?? null;
        $valid = false;

        if ($meta && !$this->isExpired($meta['time'])) {
            if ($this->bindIp && ($meta['ip'] ?? null) !== ($_SERVER['REMOTE_ADDR'] ?? null)) {
                $valid = false;
            } elseif ($this->bindUa && ($meta['ua'] ?? null) !== ($_SERVER['HTTP_USER_AGENT'] ?? null)) {
                $valid = false;
            } elseif ($scope && ($meta['scope'] ?? null) !== $scope) {
                $valid = false;
            } else {
                $valid = true;
            }
        }

        if ($valid) {
            $store[$this->oneTimeTokensKey][$token]['used']++;

            if ($store[$this->oneTimeTokensKey][$token]['used'] >= $this->tokenUsageLimit) {
                unset($store[$this->oneTimeTokensKey][$token]);
            }

            if ($this->replayProtection) {
                $this->usedTokens[$token] = true;
            }
            $this->stats['validates']++;
        } else {
            $this->stats['fails']++;
        }

        return $valid;
    }

    // Token usage limit
    public function setTokenUsageLimit($n)
    {
        $this->tokenUsageLimit = $n;
    }

    // Token revocation
    public function revokeToken($token)
    {
        $this->revokedTokens[$token] = true;
    }
    public function isRevoked($token)
    {
        return !empty($this->revokedTokens[$token]);
    }

    // Generate a per-form token
    public function generateFormToken($formId, $user = null)
    {
        $store = &$this->store();

        if ($this->multiTokenPerForm) {
            if (!isset($store[$this->formTokensKey][$formId])) {
                $store[$this->formTokensKey][$formId] = [];
            }
            $token = bin2hex(random_bytes(32));
            $meta  = [ 'time' => time() ];

            if ($user || $this->userKey) {
                $meta['user'] = $user ?: $this->userKey;
            }
            $store[$this->formTokensKey][$formId][$token] = $meta;
            $this->tokenMeta[$token]                      = $meta;
            $this->trigger('generate', $token, $formId);
            $this->auditTrail[] = [ 'event' => 'generate_form', 'form' => $formId, 'token' => $token, 'time' => time() ];

            return $token;
        } else {
            if (empty($store[$this->formTokensKey][$formId]) || $this->isExpired($store[$this->formTokensKey][$formId]['time'] ?? 0)) {
                $token = bin2hex(random_bytes(32));
                $meta  = [ 'time' => time() ];

                if ($user || $this->userKey) {
                    $meta['user'] = $user ?: $this->userKey;
                }
                $store[$this->formTokensKey][$formId] = [ 'token' => $token, 'time' => time() ];
                $this->tokenMeta[$token]              = $meta;
                $this->trigger('generate', $token, $formId);
                $this->auditTrail[] = [ 'event' => 'generate_form', 'form' => $formId, 'token' => $token, 'time' => time() ];
            }

            return $store[$this->formTokensKey][$formId]['token'];
        }
    }

    public function getToken()
    {
        $store = &$this->store();

        return $store[$this->sessionKey] ?? $this->generateToken();
    }

    public function getFormToken($formId, $user = null)
    {
        $store = &$this->store();

        if ($this->multiTokenPerForm) {
            // Return all tokens for this form (optionally for user)
            if (!isset($store[$this->formTokensKey][$formId])) {
                return [];
            }

            if ($user || $this->userKey) {
                $tokens = [];

                foreach ($store[$this->formTokensKey][$formId] as $token => $meta) {
                    if (($meta['user'] ?? null) === ($user ?: $this->userKey)) {
                        $tokens[] = $token;
                    }
                }

                return $tokens;
            }

            return array_keys($store[$this->formTokensKey][$formId]);
        } else {
            return $store[$this->formTokensKey][$formId]['token'] ?? $this->generateFormToken($formId, $user);
        }
    }

    public function validate($token, $formId = null, $scope = null, $user = null)
    {
        $store = &$this->store();
        $valid = false;

        if ($formId) {
            if ($this->multiTokenPerForm) {
                $tokens = $store[$this->formTokensKey][$formId] ?? [];
                $meta   = $tokens[$token]                       ?? null;
                $valid  = $meta && !$this->isExpired($meta['time'] ?? 0);

                if ($user || $this->userKey) {
                    $valid = $valid && (($meta['user'] ?? null) === ($user ?: $this->userKey));
                }
            } else {
                $valid = isset($store[$this->formTokensKey][$formId]['token']) && hash_equals($store[$this->formTokensKey][$formId]['token'], $token) && !$this->isExpired($store[$this->formTokensKey][$formId]['time'] ?? 0);

                if ($user || $this->userKey) {
                    $valid = $valid && (($store[$this->formTokensKey][$formId]['user'] ?? null) === ($user ?: $this->userKey));
                }
            }
        } else {
            $valid = hash_equals($this->getToken(), $token) && !$this->isExpired($store[$this->sessionKey.'_time'] ?? 0);
        }

        if ($this->isRevoked($token)) {
            $valid = false;
        }

        if ($this->bindIp && ($_SERVER['REMOTE_ADDR'] ?? null) !== ($store[$this->sessionKey.'_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? null))) {
            $valid = false;
        }

        if ($this->bindUa && ($_SERVER['HTTP_USER_AGENT'] ?? null) !== ($store[$this->sessionKey.'_ua'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null))) {
            $valid = false;
        }

        if ($scope && !in_array($scope, $this->scopes)) {
            $valid = false;
        }

        if ($this->replayProtection && isset($this->usedTokens[$token])) {
            $valid = false;
        }

        if ($this->externalVerifier && !call_user_func($this->externalVerifier, $token, $formId, $scope, $user)) {
            $valid = false;
        }

        if ($valid) {
            $this->tokenUsageCounts[$token] = ($this->tokenUsageCounts[$token] ?? 0) + 1;

            if ($this->tokenUsageCounts[$token] >= $this->tokenUsageLimit) {
                $this->revokeToken($token);
            }

            if ($this->replayProtection) {
                $this->usedTokens[$token] = true;
            }
        }
        $this->trigger('validate', $token, $formId, $valid);
        $this->auditTrail[] = [ 'event' => 'validate', 'token' => $token, 'form' => $formId, 'scope' => $scope, 'user' => $user, 'result' => $valid, 'time' => time() ];

        if (!$valid) {
            $this->lastError = 'Invalid, expired, revoked, or replayed CSRF token';
            $this->trigger('fail', $token, $formId);
            $this->stats['fails']++;

            if ($this->complianceMode) {
                $this->log[] = [ 'event' => 'fail', 'token' => $token, 'form' => $formId, 'scope' => $scope, 'user' => $user, 'time' => time() ];
            }
        } else {
            $this->stats['validates']++;

            if ($this->complianceMode) {
                $this->log[] = [ 'event' => 'pass', 'token' => $token, 'form' => $formId, 'scope' => $scope, 'user' => $user, 'time' => time() ];
            }
        }

        return $valid;
    }
    // Configurable hash algorithm
    public function setHashAlgo($algo)
    {
        $this->hashAlgo = $algo;
    }
    public function getHashAlgo()
    {
        return $this->hashAlgo;
    }

    // Per-user tokens
    public function setUserKey($user)
    {
        $this->userKey = $user;
    }
    public function getUserKey()
    {
        return $this->userKey;
    }

    // Token rotation hooks
    public function onRotate($cb)
    {
        $this->rotationHooks[] = $cb;
    }

    // Advanced logging
    public function getLog()
    {
        return $this->log;
    }
    public function clearLog()
    {
        $this->log = [];
    }

    // Compliance mode
    public function enableComplianceMode($on = true)
    {
        $this->complianceMode = $on;
    }
    public function isComplianceMode()
    {
        return $this->complianceMode;
    }

    // External verifier integration
    public function setExternalVerifier($cb)
    {
        $this->externalVerifier = $cb;
    }
    // Batch validation
    public function batchValidate(array $tokens, $formId = null, $scope = null)
    {
        $results = [];

        foreach ($tokens as $token) {
            $results[$token] = $this->validate($token, $formId, $scope);
        }

        return $results;
    }

    // Token scopes
    public function setScopes(array $scopes)
    {
        $this->scopes = $scopes;
    }
    public function getScopes()
    {
        return $this->scopes;
    }

    // Token binding
    public function bindIp($on = true)
    {
        $this->bindIp = $on;
    }
    public function bindUa($on = true)
    {
        $this->bindUa = $on;
    }

    // Replay protection
    public function enableReplayProtection($on = true)
    {
        $this->replayProtection = $on;
    }
    public function clearUsedTokens()
    {
        $this->usedTokens = [];
    }

    // Token garbage collection
    public function gc()
    {
        $store = &$this->store();

        // Remove expired one-time tokens
        if (isset($store[$this->oneTimeTokensKey])) {
            foreach ($store[$this->oneTimeTokensKey] as $token => $meta) {
                if ($this->isExpired($meta['time'] ?? 0)) {
                    unset($store[$this->oneTimeTokensKey][$token]);
                }
            }
        }

        // Remove expired form tokens
        if (isset($store[$this->formTokensKey])) {
            foreach ($store[$this->formTokensKey] as $formId => $meta) {
                if ($this->isExpired($meta['time'] ?? 0)) {
                    unset($store[$this->formTokensKey][$formId]);
                }
            }
        }
    }

    // Export/import
    public function export($file)
    {
        // Security: Validate file path to prevent directory traversal
        $realpath = realpath(dirname($file));
        if ($realpath === false || strpos($realpath, realpath(sys_get_temp_dir())) !== 0) {
            throw new \InvalidArgumentException('Invalid file path for CSRF export');
        }
        
        $data = json_encode([
            'store'         => $this->store(),
            'usedTokens'    => $this->usedTokens,
            'revokedTokens' => $this->revokedTokens,
            'scopes'        => $this->scopes,
        ], JSON_THROW_ON_ERROR);
        
        if (file_put_contents($file, $data) === false) {
            throw new \RuntimeException('Failed to write CSRF export file');
        }
    }
    public function import($file)
    {
        if (file_exists($file)) {
            // Security: Validate file path to prevent directory traversal
            $realpath = realpath($file);
            if ($realpath === false || strpos($realpath, realpath(sys_get_temp_dir())) !== 0) {
                throw new \InvalidArgumentException('Invalid file path for CSRF import');
            }
            
            $contents = file_get_contents($file);
            if ($contents === false) {
                throw new \RuntimeException('Failed to read CSRF import file');
            }
            
            // Security: Use JSON instead of unserialize to prevent code execution
            $data = json_decode($contents, true);
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid JSON in CSRF import file: ' . json_last_error_msg());
            }

            foreach ($data['store'] as $k => $v) {
                $this->store()[$k] = $v;
            }
            $this->usedTokens    = $data['usedTokens']    ?? [];
            $this->revokedTokens = $data['revokedTokens'] ?? [];
            $this->scopes        = $data['scopes']        ?? [];
        }
    }

    // Event replay
    public function replayEvents(array $events)
    {
        foreach ($events as $e) {
            $this->trigger($e['event'], ...($e['args'] ?? []));
        }
    }

    // Health check
    public function healthCheck()
    {
        return is_array($this->stats) && isset($this->stats['generates']);
    }
    // Token entropy check
    protected function isStrong($token)
    {
        return strlen($token) >= 32 && preg_match('/[a-f0-9]{32,}/', $token);
    }

    // Policy integration
    public function setPolicy($policy)
    {
        $this->policy = $policy;
    }
    public function getPolicy()
    {
        return $this->policy;
    }
    // Stats
    public function stats()
    {
        return $this->stats;
    }

    // Advanced test utilities
    public function fakeArrayStore($data = [])
    {
        $this->backend    = 'array';
        $this->arrayStore = $data;
    }
    public function clearArrayStore()
    {
        $this->arrayStore = [];
    }

    public function inputField($formId = null)
    {
        $token = $formId ? $this->getFormToken($formId) : $this->getToken();
        $token = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
        $name  = $formId ? '_csrf_'.$formId : '_csrf';

        return '<input type="hidden" name="' . $name . '" value="' . $token . '">';
    }

    // Token rotation
    public function rotateToken()
    {
        $store                            = &$this->store();
        $store[$this->sessionKey]         = bin2hex(random_bytes(32));
        $store[$this->sessionKey.'_time'] = time();
        $this->trigger('rotate', $store[$this->sessionKey]);
        $this->auditTrail[] = [ 'event' => 'rotate', 'token' => $store[$this->sessionKey], 'time' => time() ];

        return $store[$this->sessionKey];
    }

    // Expiration check
    protected function isExpired($time)
    {
        return $time > 0 && (time() - $time) > $this->tokenTtl;
    }

    // AJAX/header support
    public function headerToken()
    {
        return $this->getToken();
    }
    public function validateHeader($headerToken)
    {
        return $this->validate($headerToken);
    }

    // Set backend (session, redis, etc.)
    public function setBackend($backend)
    {
        $this->backend = $backend;
    }

    // Set token TTL
    public function setTtl($ttl)
    {
        $this->tokenTtl = $ttl;
    }

    // Get last error
    public function lastError()
    {
        return $this->lastError;
    }

    // Test utilities
    public function fake($token = null)
    {
        $store                            = &$this->store();
        $store[$this->sessionKey]         = $token ?: 'testtoken';
        $store[$this->sessionKey.'_time'] = time();
    }
}
