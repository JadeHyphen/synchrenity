<?php
namespace Synchrenity\RateLimit;

class SynchrenityRateLimiter {
    protected $auditTrail;
    protected $backend = 'memory'; // memory, file
    protected $limits = [ 'default' => [ 'limit' => 10, 'window' => 60 ] ]; // 10 actions per 60s
    protected $data = [];
    protected $filePath;
    protected $hooks = [];

    public function __construct($backend = 'memory', $options = []) {
        $this->backend = $backend;
        if ($backend === 'file') {
            $this->filePath = $options['filePath'] ?? __DIR__ . '/ratelimit.data';
            if (!file_exists($this->filePath)) file_put_contents($this->filePath, json_encode([]));
            $this->loadFileData();
        }
    }

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
        $now = time();
        $conf = $this->limits[$action] ?? $this->limits['default'];
        $limit = $conf['limit'];
        $window = $conf['window'];
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
        if ($allowed) {
            $this->data[$action][$user][] = $now;
            foreach ($this->hooks as $hook) {
                call_user_func($hook, $user, $action, $count+1, $limit);
            }
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
        return $allowed;
    }

    // Internal: load/save file data
    protected function loadFileData() {
        $data = @file_get_contents($this->filePath);
        $this->data = $data ? json_decode($data, true) : [];
    }
    protected function saveFileData() {
        file_put_contents($this->filePath, json_encode($this->data));
    }

    // Audit helper
    protected function audit($action, $userId = null, $meta = []) {
        if ($this->auditTrail) {
            $this->auditTrail->log($action, [], $userId, $meta);
        }
    }
}
