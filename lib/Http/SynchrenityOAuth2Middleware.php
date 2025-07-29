<?php

declare(strict_types=1);

namespace Synchrenity\Http;

class SynchrenityOAuth2Middleware
{
    protected $oauth2Provider;
    protected $requiredScopes = [];
    protected $hooks          = [];
    protected $plugins        = [];
    protected $events         = [];
    protected $metrics        = [
        'calls'      => 0,
        'success'    => 0,
        'fail'       => 0,
        'refresh'    => 0,
        'introspect' => 0,
        'pkce'       => 0,
    ];
    protected $providers      = [];
    protected $complianceMode = false;
    protected $log            = [];
    protected $jwkSet         = [];
    protected $jwtDecoder     = null;

    public function __construct($oauth2Provider, $requiredScopes = [], $options = [])
    {
        $this->oauth2Provider = $oauth2Provider;
        $this->requiredScopes = $requiredScopes;

        if (isset($options['complianceMode'])) {
            $this->complianceMode = $options['complianceMode'];
        }

        if (isset($options['jwkSet'])) {
            $this->jwkSet = $options['jwkSet'];
        }

        if (isset($options['jwtDecoder'])) {
            $this->jwtDecoder = $options['jwtDecoder'];
        }

        if (isset($options['providers'])) {
            $this->providers = $options['providers'];
        }
    }

    public function handle($request, $next)
    {
        $this->metrics['calls']++;
        $token = $this->extractToken($request);

        if (!$token) {
            $this->metrics['fail']++;
            $this->logEvent('fail', ['reason' => 'missing_token']);
            $this->triggerEvent('fail', ['reason' => 'missing_token']);

            return $this->unauthorized('Missing OAuth2 token');
        }

        // PKCE support (if code_verifier present)
        if ($request->query()['code_verifier'] ?? null) {
            $this->metrics['pkce']++;
            $this->triggerEvent('pkce', $request->query()['code_verifier']);
        }
        // Multi-provider support
        $provider = $this->oauth2Provider;

        if (!empty($this->providers)) {
            $provider = $this->selectProvider($request, $token) ?? $this->oauth2Provider;
        }
        // Token validation (real implementation should verify with provider)
        $tokenInfo = $this->validateToken($token, $provider);

        if (!$tokenInfo['valid']) {
            $this->metrics['fail']++;
            $this->logEvent('fail', ['reason' => 'invalid_token']);
            $this->triggerEvent('fail', ['reason' => 'invalid_token']);

            return $this->unauthorized('Invalid OAuth2 token');
        }

        // Token introspection (optional)
        if (is_callable([$provider, 'introspect'])) {
            $this->metrics['introspect']++;
            $introspect = $provider->introspect($token);

            if (is_array($introspect)) {
                $tokenInfo = array_merge($tokenInfo, $introspect);
            }
        }

        // Scope check
        if (!empty($this->requiredScopes) && !$this->hasRequiredScopes($tokenInfo)) {
            $this->metrics['fail']++;
            $this->logEvent('fail', ['reason' => 'insufficient_scope']);
            $this->triggerEvent('fail', ['reason' => 'insufficient_scope']);

            return $this->forbidden('Insufficient OAuth2 scope');
        }

        // JWT/JWK validation (optional)
        if ($this->jwtDecoder && is_callable($this->jwtDecoder)) {
            $jwtInfo = call_user_func($this->jwtDecoder, $token, $this->jwkSet);

            if (is_array($jwtInfo)) {
                $tokenInfo = array_merge($tokenInfo, $jwtInfo);
            }
        }

        // Hooks/plugins
        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'beforeNext'])) {
                $plugin->beforeNext($request, $tokenInfo, $this);
            }
        }

        foreach ($this->hooks as $hook) {
            call_user_func($hook, $request, $tokenInfo, $this);
        }
        $this->metrics['success']++;
        $this->logEvent('success', $tokenInfo);
        $this->triggerEvent('success', $tokenInfo);

        // Attach token info to request (if supported)
        if (method_exists($request, 'setOAuth2TokenInfo')) {
            $request->setOAuth2TokenInfo($tokenInfo);
        }

        return $next($request);
    }

    protected function selectProvider($request, $token)
    {
        // Example: select by issuer, audience, etc.
        foreach ($this->providers as $provider) {
            if (is_callable([$provider, 'matches']) && $provider->matches($request, $token)) {
                return $provider;
            }
        }

        return null;
    }

    protected function extractToken($request)
    {
        if (method_exists($request, 'bearerToken')) {
            return $request->bearerToken();
        }
        $header = method_exists($request, 'getHeader') ? $request->getHeader('Authorization') : null;

        if ($header && stripos($header, 'Bearer ') === 0) {
            return substr($header, 7);
        }

        return null;
    }

    protected function validateToken($token, $provider = null)
    {
        $provider = $provider ?: $this->oauth2Provider;

        // Real implementation: call $provider->validate($token) or similar
        if (is_callable([$provider, 'validate'])) {
            $info = $provider->validate($token);

            return is_array($info) ? $info : ['valid' => !!$info];
        }

        // Simulate: accept any non-empty token as valid
        return [ 'valid' => !empty($token), 'scopes' => ['*'] ];
    }
    // Token refresh (optional)
    public function refreshToken($refreshToken)
    {
        $this->metrics['refresh']++;

        if (is_callable([$this->oauth2Provider, 'refresh'])) {
            return $this->oauth2Provider->refresh($refreshToken);
        }

        return null;
    }

    // Logging
    protected function logEvent($event, $data)
    {
        if ($this->complianceMode) {
            $this->log[] = [ 'event' => $event, 'data' => $data, 'time' => time() ];
        }
    }

    protected function hasRequiredScopes($tokenInfo)
    {
        if (!isset($tokenInfo['scopes'])) {
            return false;
        }

        foreach ($this->requiredScopes as $scope) {
            if (!in_array($scope, $tokenInfo['scopes'])) {
                return false;
            }
        }

        return true;
    }

    protected function unauthorized($msg)
    {
        $resp = new \Synchrenity\Http\SynchrenityResponse($msg, 401);
        $resp->setHeader('WWW-Authenticate', 'Bearer realm="Synchrenity"');

        return $resp;
    }
    protected function forbidden($msg)
    {
        return new \Synchrenity\Http\SynchrenityResponse($msg, 403);
    }

    // Plugin system
    public function registerPlugin($plugin)
    {
        $this->plugins[] = $plugin;
    }

    // Hook system
    public function addHook(callable $hook)
    {
        $this->hooks[] = $hook;
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

    // Introspection
    public function getRequiredScopes()
    {
        return $this->requiredScopes;
    }
    public function getProvider()
    {
        return $this->oauth2Provider;
    }

}
