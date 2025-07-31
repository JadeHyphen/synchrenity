<?php

declare(strict_types=1);

namespace Synchrenity\Http;

class SynchrenityApiRateLimitMiddleware
{
    protected $rateLimiter;
    protected $roleResolver;
    protected array $plugins = [];
    protected array $events  = [];
    protected array $metrics = [
        'allowed' => 0,
        'blocked' => 0,
        'error'   => 0,
    ];
    protected array $context = [];
    protected $policyResolver;

    public function __construct($rateLimiter, ?callable $roleResolver = null, ?callable $policyResolver = null)
    {
        $this->rateLimiter    = $rateLimiter;
        $this->roleResolver   = $roleResolver;
        $this->policyResolver = $policyResolver;
    }

    public function handle($request, callable $next)
    {
        try {
            $endpoint = $request->getMethod() . ':' . $request->getPath();
            $user     = $request->getUserId() ?? $request->getIp() ?? 'guest';
            $role     = $this->roleResolver ? call_user_func($this->roleResolver, $request) : 'user';
            $policy   = $this->policyResolver ? call_user_func($this->policyResolver, $request, $user, $role, $endpoint) : null;
            $context  = [
                'user'     => $user,
                'role'     => $role,
                'endpoint' => $endpoint,
                'ip'       => method_exists($request, 'getIp') ? $request->getIp() : null,
                'policy'   => $policy,
            ];
        $this->context = $context;
        $allowed       = false;
        $error         = null;

        try {
            $allowed = $this->rateLimiter->check($user, $role, $endpoint, $policy);
        } catch (\Throwable $e) {
            $this->metrics['error']++;
            $error = $e;
            $this->triggerEvent('error', $context + ['exception' => $e]);

            foreach ($this->plugins as $plugin) {
                if (is_callable([$plugin, 'onError'])) {
                    $plugin->onError($request, $e, $this);
                }
            }

            return new \Synchrenity\Http\SynchrenityResponse('Rate limit error', 500);
        }

        if (!$allowed) {
            $this->metrics['blocked']++;
            $this->triggerEvent('blocked', $context);

            foreach ($this->plugins as $plugin) {
                if (is_callable([$plugin, 'onBlocked'])) {
                    $plugin->onBlocked($request, $this);
                }
            }
            $response = new \Synchrenity\Http\SynchrenityResponse('API rate limit exceeded', 429);
            $this->setRateLimitHeaders($response, $user, $role, $endpoint, $policy);

            return $response;
        }
        $this->metrics['allowed']++;
        $this->triggerEvent('allowed', $context);

        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'onAllowed'])) {
                $plugin->onAllowed($request, $this);
            }
        }
        $response = $next($request);
        $this->setRateLimitHeaders($response, $user, $role, $endpoint, $policy);

        return $response;
        } catch (\Throwable $e) {
            $this->metrics['error']++;
            return new \Synchrenity\Http\SynchrenityResponse('Middleware error: ' . $e->getMessage(), 500);
        }
    }

    protected function setRateLimitHeaders($response, $user, $role, $endpoint, $policy)
    {
        if (!method_exists($response, 'header')) {
            return;
        }
        $info = $this->rateLimiter->info($user, $role, $endpoint, $policy);
        $response->header('X-RateLimit-Limit', $info['limit'] ?? '');
        $response->header('X-RateLimit-Remaining', $info['remaining'] ?? '');
        $response->header('X-RateLimit-Reset', $info['reset'] ?? '');
        $response->header('X-RateLimit-Policy', $info['policy'] ?? '');
        $response->header('X-RateLimit-Window', $info['window'] ?? '');
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

    // Context
    public function setContext($key, $value)
    {
        $this->context[$key] = $value;
    }
    public function getContext($key, $default = null)
    {
        return $this->context[$key] ?? $default;
    }

    // Metrics
    public function getMetrics()
    {
        return $this->metrics;
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
}
