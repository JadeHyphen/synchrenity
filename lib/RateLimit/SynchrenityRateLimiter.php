<?php
namespace Synchrenity\RateLimit;

class SynchrenityRateLimiter {
    // --- ADVANCED: AI/ML anomaly detection (optional, pluggable) ---
    protected $anomalyDetector = null;
    public function setAnomalyDetector(callable $detector) { $this->anomalyDetector = $detector; }
    protected function detectAnomaly($user, $action, $meta = []) {
        if ($this->anomalyDetector) {
            return call_user_func($this->anomalyDetector, $user, $action, $meta, $this);
        }
        return false;
    }

    // --- ADVANCED: Distributed token bucket (multi-node, Redis/DB/Custom) ---
    protected $distributedTokenBucket = null;
    public function setDistributedTokenBucket($bucket) { $this->distributedTokenBucket = $bucket; }
    protected function useDistributedToken($user, $action, $tokens = 1) {
        if ($this->distributedTokenBucket) {
            return $this->distributedTokenBucket->consume($user, $action, $tokens);
        }
        return true;
    }

    // --- ADVANCED: Per-user behavioral fingerprinting ---
    protected $fingerprints = [];
    public function setUserFingerprint($user, $fingerprint) { $this->fingerprints[$user] = $fingerprint; }
    public function getUserFingerprint($user) { return $this->fingerprints[$user] ?? null; }
    public function matchUserFingerprint($user, $fingerprint) {
        return isset($this->fingerprints[$user]) && $this->fingerprints[$user] === $fingerprint;
    }

    // --- ADVANCED: Quota shaping (dynamic, per-user/action, time-of-day) ---
    protected $quotaShapers = [];
    public function setQuotaShaper($user, callable $cb) { $this->quotaShapers[$user] = $cb; }
    protected function getShapedQuota($user, $action) {
        if (isset($this->quotaShapers[$user])) {
            return call_user_func($this->quotaShapers[$user], $user, $action, $this);
        }
        return $this->quota[$user] ?? null;
    }

    // --- ADVANCED: Circuit breaker integration ---
    protected $circuitBreakers = [];
    public function setCircuitBreaker($action, $breaker) { $this->circuitBreakers[$action] = $breaker; }
    protected function isCircuitOpen($action) {
        if (isset($this->circuitBreakers[$action])) {
            return $this->circuitBreakers[$action]->isOpen();
        }
        return false;
    }

    // --- ADVANCED: Real-time metrics streaming (pluggable) ---
    protected $metricsStreamers = [];
    public function addMetricsStreamer(callable $cb) { $this->metricsStreamers[] = $cb; }
    protected function streamMetrics($event, $meta = []) {
        foreach ($this->metricsStreamers as $cb) {
            call_user_func($cb, $event, $meta, $this);
        }
    }

    // --- ADVANCED: Plugin-based extensibility ---
    protected $plugins = [];
    public function registerPlugin($plugin) {
        if (is_callable([$plugin, 'register'])) $plugin->register($this);
        $this->plugins[] = $plugin;
    }
    protected $auditTrail;
    protected $ipLimits = [];
    protected $uaLimits = [];
    protected $quota = [];
    protected $whitelist = [];
    protected $blacklist = [];
    protected $buckets = [];
    protected $log = [];
    protected $complianceMode = false;
    protected $dryRun = false;
    protected $syncCallback = null;
    protected $backend = 'memory'; // memory, file, redis
    protected $limits = [ 'default' => [ 'limit' => 10, 'window' => 60 ] ]; // 10 actions per 60s
    protected $data = [];
    protected $filePath;
    protected $hooks = [];
    protected $redis = null;
    protected $burstLimits = [];
    protected $penalties = [];
    protected $lockouts = [ 'global'=>false, 'users'=>[] ];
    protected $dynamicWindows = [];
    protected $actionGroups = [];
    protected $stats = [ 'checks'=>0, 'allowed'=>0, 'denied'=>0 ];
    protected $eventHooks = [ 'allow'=>[], 'deny'=>[], 'lockout'=>[] ];

    public function __construct($backend = 'memory', $options = []) {
        if (isset($options['dryRun'])) $this->dryRun = $options['dryRun'];
        if (isset($options['complianceMode'])) $this->complianceMode = $options['complianceMode'];
        if (isset($options['syncCallback'])) $this->syncCallback = $options['syncCallback'];
    }

    // Composite limits: per user+action+IP/UA
    protected $compositeLimits = [];
    public function setCompositeLimit($user, $action, $ip, $ua, $limit, $window) {
        $key = $this->compositeKey($user, $action, $ip, $ua);
        $this->compositeLimits[$key] = [ 'limit'=>$limit, 'window'=>$window ];
    }
    protected function compositeKey($user, $action, $ip, $ua) {
        return md5(json_encode([$user, $action, $ip, $ua]));
    }

    // Sliding window log for advanced introspection
    protected $accessLog = [];
    public function getAccessLog($user = null, $action = null) {
        if ($user && $action) return $this->accessLog[$user][$action] ?? [];
        if ($user) return $this->accessLog[$user] ?? [];
        return $this->accessLog;
    }

    // Penalty escalation: increase penalty on repeated violations
    protected $penaltyEscalation = [];
    public function setPenaltyEscalation($action, $steps) { $this->penaltyEscalation[$action] = $steps; }

    // Quota exhaustion callback
    protected $quotaExhaustionCallback = null;
    public function onQuotaExhaustion(callable $cb) { $this->quotaExhaustionCallback = $cb; }

    // Lockout expiration
    protected $lockoutExpirations = [];
    public function setLockout($user, $seconds) {
        $this->lockout($user);
        $this->lockoutExpirations[$user] = time() + $seconds;
    }
    public function checkLockoutExpirations() {
        $now = time();
        foreach ($this->lockoutExpirations as $user => $expires) {
            if ($now >= $expires) $this->unlock($user);
        }
    }

    // Thread-safe file locking for file backend
    protected function saveFileData() {
        if ($this->filePath) {
            $fp = fopen($this->filePath, 'c+');
            if ($fp) {
                flock($fp, LOCK_EX);
                ftruncate($fp, 0);
                fwrite($fp, json_encode($this->data));
                fflush($fp);
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }
    }

    // Whitelist/blacklist helpers
    public function isWhitelisted($user) { return !empty($this->whitelist[$user]); }
    public function isBlacklisted($user) { return !empty($this->blacklist[$user]); }

    // Distributed sync helper
    public function sync() { if ($this->syncCallback) call_user_func($this->syncCallback, $this); }

    // Penalty logic
    protected function penalize($user, $action) {
        $seconds = $this->penalties[$action] ?? 60;
        // Escalate penalty if configured
        if (isset($this->penaltyEscalation[$action])) {
            $step = $this->penaltyEscalation[$action];
            $seconds *= $step;
            $this->penaltyEscalation[$action]++;
        }
        $this->setLockout($user, $seconds);
        $this->audit('penalty', $user, [ 'action'=>$action, 'seconds'=>$seconds ]);
    }

    // File backend helpers

    // Audit helper
    protected function audit($action, $userId = null, $meta = []) {
        if ($this->auditTrail) {
            $this->auditTrail->log($action, [], $userId, $meta);
        }
    }

    // Per-IP and UA limits
    public function setIpLimit($ip, $limit, $window) { $this->ipLimits[$ip] = [ 'limit'=>$limit, 'window'=>$window ]; }
    public function setUaLimit($ua, $limit, $window) { $this->uaLimits[$ua] = [ 'limit'=>$limit, 'window'=>$window ]; }

    // Quota

    // Whitelist/blacklist

    // Buckets

    // Reset

    // Introspection

    // Dry-run mode

    // Distributed sync

    // Advanced logging

    // Compliance mode

    // Event hooks
    public function on($event, $cb) { if (isset($this->eventHooks[$event])) $this->eventHooks[$event][] = $cb; }
    protected function trigger($event, ...$args) { if (isset($this->eventHooks[$event])) foreach ($this->eventHooks[$event] as $cb) call_user_func_array($cb, $args); }

    // Burst/penalty
    public function setBurstLimit($action, $burst) { $this->burstLimits[$action] = $burst; }
    public function setPenalty($action, $seconds) { $this->penalties[$action] = $seconds; }

    // Global/user lockout
    public function lockout($user = null) { if ($user) $this->lockouts['users'][$user] = true; else $this->lockouts['global'] = true; }
    public function unlock($user = null) { if ($user) unset($this->lockouts['users'][$user]); else $this->lockouts['global'] = false; }
    public function isLockedOut($user) { return $this->lockouts['global'] || !empty($this->lockouts['users'][$user]); }

    // Dynamic window
    public function setDynamicWindow($action, callable $cb) { $this->dynamicWindows[$action] = $cb; }

    // Action groups
    public function setActionGroup($group, array $actions) { $this->actionGroups[$group] = $actions; }
    public function getActionGroup($group) { return $this->actionGroups[$group] ?? []; }

    // Stats
    public function stats() { return $this->stats; }

    // Import/export
    public function export($file) { file_put_contents($file, serialize([$this->data, $this->limits, $this->lockouts])); }
    public function import($file) { if (file_exists($file)) { list($this->data, $this->limits, $this->lockouts) = unserialize(file_get_contents($file)); } }

    // Health check
    public function healthCheck() { return is_array($this->data) && isset($this->limits['default']); }

    public function setAuditTrail($auditTrail) {
        $this->auditTrail = $auditTrail;
    }
    public function setLimit($action, $limit, $window) {
        $this->limits[$action] = [ 'limit' => $limit, 'window' => $window ];
    }
    public function addHook(callable $hook) {
        $this->hooks[] = $hook;
    }

    // Check rate limit for user/action
    public function check($user, $action) {
        // --- Circuit breaker check ---
        if ($this->isCircuitOpen($action)) {
            $this->streamMetrics('circuit_open', ['user'=>$user,'action'=>$action]);
            $this->audit('circuit_open', $user, ['action'=>$action]);
            return false;
        }

        // --- AI/ML anomaly detection ---
        $meta = ['ip'=>$_SERVER['REMOTE_ADDR'] ?? null, 'ua'=>$_SERVER['HTTP_USER_AGENT'] ?? null];
        if ($this->detectAnomaly($user, $action, $meta)) {
            $this->streamMetrics('anomaly', ['user'=>$user,'action'=>$action]);
            $this->audit('anomaly', $user, ['action'=>$action]);
            return false;
        }

        // --- Distributed token bucket ---
        if (!$this->useDistributedToken($user, $action, 1)) {
            $this->streamMetrics('token_bucket_denied', ['user'=>$user,'action'=>$action]);
            $this->audit('token_bucket_denied', $user, ['action'=>$action]);
            return false;
        }

        // --- Quota shaping ---
        $shapedQuota = $this->getShapedQuota($user, $action);
        if ($shapedQuota !== null && $this->stats['allowed'] >= $shapedQuota) {
            $this->streamMetrics('quota_shaped_denied', ['user'=>$user,'action'=>$action]);
            $this->audit('quota_shaped_denied', $user, ['action'=>$action]);
            return false;
        }

        // --- Behavioral fingerprinting (optional, for advanced anti-abuse) ---
        if (isset($this->fingerprints[$user])) {
            $expected = $this->fingerprints[$user];
            $actual = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if ($expected && $expected !== $actual) {
                $this->streamMetrics('fingerprint_mismatch', ['user'=>$user,'action'=>$action]);
                $this->audit('fingerprint_mismatch', $user, ['action'=>$action]);
                return false;
            }
        }
        $this->checkLockoutExpirations();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        // Composite limit check
        $compositeKey = $this->compositeKey($user, $action, $ip, $ua);
        if (isset($this->compositeLimits[$compositeKey])) {
            $conf = $this->compositeLimits[$compositeKey];
            $limit = $conf['limit'];
            $window = $conf['window'];
            $now = time();
            if (!isset($this->data['composite'][$compositeKey])) $this->data['composite'][$compositeKey] = [];
            $this->data['composite'][$compositeKey] = array_filter($this->data['composite'][$compositeKey], function($ts) use ($now, $window) { return $ts > $now - $window; });
            $count = count($this->data['composite'][$compositeKey]);
            if ($count >= $limit) {
                $this->stats['denied']++;
                if ($this->complianceMode) $this->log[] = [ 'event'=>'composite_limit', 'key'=>$compositeKey, 'count'=>$count, 'limit'=>$limit, 'time'=>$now ];
                return false;
            }
            $this->data['composite'][$compositeKey][] = $now;
        }
        // Sliding window access log
        if (!isset($this->accessLog[$user])) $this->accessLog[$user] = [];
        if (!isset($this->accessLog[$user][$action])) $this->accessLog[$user][$action] = [];
        $this->accessLog[$user][$action][] = [ 'time'=>time(), 'ip'=>$ip, 'ua'=>$ua ]; // keep all for now
        // Quota exhaustion callback
        if ($this->quota && isset($this->quota[$user]) && $this->stats['allowed'] >= $this->quota[$user]) {
            if ($this->quotaExhaustionCallback) call_user_func($this->quotaExhaustionCallback, $user, $action);
        }
        if ($this->isWhitelisted($user)) return true;
        if ($this->isBlacklisted($user)) return false;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        if ($ip && isset($this->ipLimits[$ip])) {
            $conf = $this->ipLimits[$ip];
            $limit = $conf['limit'];
            $window = $conf['window'];
            $now = time();
            if (!isset($this->data['ip'][$ip])) $this->data['ip'][$ip] = [];
            $this->data['ip'][$ip] = array_filter($this->data['ip'][$ip], function($ts) use ($now, $window) { return $ts > $now - $window; });
            $count = count($this->data['ip'][$ip]);
            if ($count >= $limit) {
                $this->stats['denied']++;
                if ($this->complianceMode) $this->log[] = [ 'event'=>'ip_limit', 'ip'=>$ip, 'count'=>$count, 'limit'=>$limit, 'time'=>$now ];
                return false;
            }
            $this->data['ip'][$ip][] = $now;
        }
        if ($ua && isset($this->uaLimits[$ua])) {
            $conf = $this->uaLimits[$ua];
            $limit = $conf['limit'];
            $window = $conf['window'];
            $now = time();
            if (!isset($this->data['ua'][$ua])) $this->data['ua'][$ua] = [];
            $this->data['ua'][$ua] = array_filter($this->data['ua'][$ua], function($ts) use ($now, $window) { return $ts > $now - $window; });
            $count = count($this->data['ua'][$ua]);
            if ($count >= $limit) {
                $this->stats['denied']++;
                if ($this->complianceMode) $this->log[] = [ 'event'=>'ua_limit', 'ua'=>$ua, 'count'=>$count, 'limit'=>$limit, 'time'=>$now ];
                return false;
            }
            $this->data['ua'][$ua][] = $now;
        }
        if ($this->quota && isset($this->quota[$user]) && $this->stats['allowed'] >= $this->quota[$user]) {
            $this->stats['denied']++;
            if ($this->complianceMode) $this->log[] = [ 'event'=>'quota', 'user'=>$user, 'quota'=>$this->quota[$user], 'time'=>time() ];
            return false;
        }
        if ($this->dryRun) return true;
        if ($this->syncCallback) $this->sync();
        $this->stats['checks']++;
        if ($this->isLockedOut($user)) {
            $this->trigger('lockout', $user, $action);
            $this->stats['denied']++;
            $this->audit('lockout', $user, [ 'action'=>$action ]);
            return false;
        }
        $now = time();
        $conf = $this->limits[$action] ?? $this->limits['default'];
        $limit = $conf['limit'];
        $window = $conf['window'];
        if (isset($this->dynamicWindows[$action])) $window = call_user_func($this->dynamicWindows[$action], $user, $action, $conf);
        // Redis backend
        if ($this->backend === 'redis' && $this->redis) {
            $key = "ratelimit:{$action}:{$user}";
            $count = (int)$this->redis->get($key);
            if ($count < $limit) {
                $this->redis->multi();
                $this->redis->incr($key);
                $this->redis->expire($key, $window);
                $this->redis->exec();
                $this->trigger('allow', $user, $action, $count+1, $limit);
                $this->stats['allowed']++;
                $this->audit('rate_limit_check', $user, [ 'action'=>$action, 'count'=>$count+1, 'limit'=>$limit, 'window'=>$window, 'allowed'=>true ]);
                return true;
            } else {
                $this->trigger('deny', $user, $action, $count, $limit);
                $this->stats['denied']++;
                $this->audit('rate_limit_check', $user, [ 'action'=>$action, 'count'=>$count, 'limit'=>$limit, 'window'=>$window, 'allowed'=>false ]);
                return false;
            }
        }
        // Memory/file backend
        if (!isset($this->data[$action][$user])) {
            $this->data[$action][$user] = [];
        }
        // Remove old timestamps (sliding window)
        $this->data[$action][$user] = array_filter(
            $this->data[$action][$user],
            function($ts) use ($now, $window) { return $ts > $now - $window; }
        );
        $count = count($this->data[$action][$user]);
        $allowed = $count < $limit;
        // Burst/penalty
        if ($allowed && isset($this->burstLimits[$action]) && $count+1 > $this->burstLimits[$action]) {
            $this->penalize($user, $action);
            $allowed = false;
        }
        if ($allowed) {
            $this->data[$action][$user][] = $now;
            foreach ($this->hooks as $hook) {
                call_user_func($hook, $user, $action, $count+1, $limit);
            }
            $this->trigger('allow', $user, $action, $count+1, $limit);
            $this->stats['allowed']++;
            if ($this->complianceMode) $this->log[] = [ 'event'=>'allow', 'user'=>$user, 'action'=>$action, 'count'=>$count+1, 'limit'=>$limit, 'time'=>$now ];
            $this->streamMetrics('allow', ['user'=>$user,'action'=>$action,'count'=>$count+1,'limit'=>$limit]);
        } else {
            $this->trigger('deny', $user, $action, $count, $limit);
            $this->stats['denied']++;
            if ($this->complianceMode) $this->log[] = [ 'event'=>'deny', 'user'=>$user, 'action'=>$action, 'count'=>$count, 'limit'=>$limit, 'time'=>$now ];
            $this->streamMetrics('deny', ['user'=>$user,'action'=>$action,'count'=>$count,'limit'=>$limit]);
        }
        if ($this->backend === 'file') {
            $this->saveFileData();
        }
        $meta = [
            'user' => $user,
            'action' => $action,
            'count' => $count+($allowed?1:0),
            'limit' => $limit,
            'window' => $window,
            'allowed' => $allowed
        ];
        $this->audit('rate_limit_check', $user, $meta);
        // --- Plugin hooks (post-check) ---
        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'afterCheck'])) {
                $plugin->afterCheck($user, $action, $allowed, $meta, $this);
            }
        }
        return $allowed;
    }
}

