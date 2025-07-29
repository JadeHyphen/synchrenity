<?php

declare(strict_types=1);

namespace Synchrenity\API;

class SynchrenityApiRateLimiter
{
    protected $limits    = [];
    protected $analytics = [];
    protected $auditTrail;
    protected $burstLimits = [];
    protected $dynamicConfig;
    protected $hooks   = [];
    protected $plugins = [];
    protected $events  = [];
    protected $metrics = [
        'checks'  => 0,
        'allowed' => 0,
        'blocked' => 0,
        'errors'  => 0,
    ];
    protected $context = [];

    public function __construct($limits = [], $burstLimits = [], $dynamicConfig = null)
    {
        $this->limits        = $limits;
        $this->burstLimits   = $burstLimits;
        $this->dynamicConfig = $dynamicConfig;
    }

    public function setAuditTrail($auditTrail)
    {
        $this->auditTrail = $auditTrail;
    }

    public function setLimit($endpoint, $role, $limit, $window)
    {
        $this->limits[$endpoint][$role] = [ 'limit' => $limit, 'window' => $window ];
    }

    public function setBurstLimit($endpoint, $role, $burst, $burstWindow)
    {
        $this->burstLimits[$endpoint][$role] = [ 'burst' => $burst, 'burstWindow' => $burstWindow ];
    }

    public function addHook(callable $hook)
    {
        $this->hooks[] = $hook;
    }

    public function check($user, $role, $endpoint)
    {
        $this->metrics['checks']++;
        $now         = time();
        $conf        = $this->limits[$endpoint][$role] ?? $this->limits['default']['default'] ?? [ 'limit' => 10, 'window' => 60 ];
        $limit       = $conf['limit'];
        $window      = $conf['window'];
        $burstConf   = $this->burstLimits[$endpoint][$role] ?? [ 'burst' => $limit, 'burstWindow' => 10 ];
        $burst       = $burstConf['burst'];
        $burstWindow = $burstConf['burstWindow'];

        if (!isset($this->analytics[$endpoint][$role][$user])) {
            $this->analytics[$endpoint][$role][$user] = [];
        }
        // Sliding window for main limit
        $this->analytics[$endpoint][$role][$user] = array_filter(
            $this->analytics[$endpoint][$role][$user],
            function ($ts) use ($now, $window) { return $ts > $now - $window; }
        );
        $count = count($this->analytics[$endpoint][$role][$user]);

        // Burst window check
        $burstCount = count(array_filter(
            $this->analytics[$endpoint][$role][$user],
            function ($ts) use ($now, $burstWindow) { return $ts > $now - $burstWindow; }
        ));

        $allowed = $count < $limit && $burstCount < $burst;

        if ($allowed) {
            $this->metrics['allowed']++;
            $this->analytics[$endpoint][$role][$user][] = $now;

            if (!empty($this->hooks)) {
                foreach ($this->hooks as $hookFn) {
                    call_user_func($hookFn, $user, $role, $endpoint, $count + 1, $limit, $burstCount + 1, $burst);
                }
            }
            $this->triggerEvent('allowed', compact('user', 'role', 'endpoint', 'count', 'limit', 'burstCount', 'burst'));

            foreach ($this->plugins as $plugin) {
                if (is_callable([$plugin, 'onAllowed'])) {
                    $plugin->onAllowed($user, $role, $endpoint, $this);
                }
            }
        } else {
            $this->metrics['blocked']++;
            $this->triggerEvent('blocked', compact('user', 'role', 'endpoint', 'count', 'limit', 'burstCount', 'burst'));

            foreach ($this->plugins as $plugin) {
                if (is_callable([$plugin, 'onBlocked'])) {
                    $plugin->onBlocked($user, $role, $endpoint, $this);
                }
            }
        }

        // Dynamic config reload
        if ($this->dynamicConfig && is_callable($this->dynamicConfig)) {
            $newConf = call_user_func($this->dynamicConfig, $user, $role, $endpoint);

            if (is_array($newConf)) {
                $this->limits[$endpoint][$role] = $newConf;
            }
        }

        $meta = [
            'user'        => $user,
            'role'        => $role,
            'endpoint'    => $endpoint,
            'count'       => $count + ($allowed ? 1 : 0),
            'limit'       => $limit,
            'window'      => $window,
            'burstCount'  => $burstCount + ($allowed ? 1 : 0),
            'burst'       => $burst,
            'burstWindow' => $burstWindow,
            'allowed'     => $allowed,
        ];

        if ($this->auditTrail) {
            $this->auditTrail->log('api_rate_limit_check', [], $user, $meta);
        }
        $this->context = $meta;

        return $allowed;
    }
    // Plugin system
    public function registerPlugin($plugin)
    {
        $this->plugins[] = $plugin;
    }
    // Event system
    public function on($event, callable $cb)
    {
        $this->events[$event][] = $cb;
    }
    protected function triggerEvent($event, $data = null)
    {
        foreach ($this->events[$event] ?? [] as $cb) {
            call_user_func($cb, $data, $this);
        }
    }
    // Metrics
    public function getMetrics()
    {
        return $this->metrics;
    }
    // Context
    public function setContext($key, $value)
    {
        $this->context[$key] = $value;
    }
    public function getContext($key, $default = null)
    {
        return $this->context[$key] ?? $default;
    }
    // Introspection
    public function getPlugins()
    {
        return $this->plugins;
    }
    public function getEvents()
    {
        return $this->events;
    }

    public function getAnalytics($endpoint = null, $role = null, $user = null)
    {
        if ($endpoint && $role && $user) {
            return $this->analytics[$endpoint][$role][$user] ?? [];
        }

        if ($endpoint && $role) {
            return $this->analytics[$endpoint][$role] ?? [];
        }

        if ($endpoint) {
            return $this->analytics[$endpoint] ?? [];
        }

        return $this->analytics;
    }

    public function exportAnalytics($format = 'json')
    {
        if ($format === 'json') {
            return json_encode($this->analytics);
        }

        // Add more formats as needed
        return $this->analytics;
    }
}
