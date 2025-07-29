<?php
namespace Synchrenity\Auth;


class SynchrenityOAuth2Provider {
    protected $providers = [];
    protected $auditTrail;
    protected $hooks = [];
    protected $stateStore = [];
    protected $plugins = [];
    protected $events = [];
    protected $metrics = [
        'auth_requests' => 0,
        'callbacks' => 0,
        'errors' => 0,
        'refreshes' => 0,
        'userinfo' => 0
    ];
    protected $context = [];

    public function __construct($providers = []) {
        foreach ($providers as $name => $config) {
            $this->addProvider($name, $config);
        }
    }

    public function setAuditTrail($auditTrail) {
        $this->auditTrail = $auditTrail;
    }

    public function addHook(callable $hook) {
        $this->hooks[] = $hook;
    }

    public function addProvider($name, $config) {
        // Validate required config keys
        $required = ['auth_url','token_url','client_id','client_secret','redirect_uri'];
        foreach ($required as $key) {
            if (!isset($config[$key])) throw new \InvalidArgumentException("Missing $key in provider config");
        }
        $this->providers[$name] = $config;
        $this->triggerEvent('add_provider', compact('name', 'config'));
        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'onAddProvider'])) $plugin->onAddProvider($name, $config, $this);
        }
    }

    public function getAuthUrl($provider, $state = null, $usePkce = false) {
        $this->metrics['auth_requests']++;
        if (!isset($this->providers[$provider])) return null;
        $conf = $this->providers[$provider];
        $url = $conf['auth_url'] . '?client_id=' . urlencode($conf['client_id']) . '&redirect_uri=' . urlencode($conf['redirect_uri']) . '&response_type=code';
        if ($state) {
            $url .= '&state=' . urlencode($state);
            $this->stateStore[$state] = [ 'provider' => $provider, 'created' => time() ];
        }
        if ($usePkce) {
            $code_verifier = bin2hex(random_bytes(32));
            $code_challenge = rtrim(strtr(base64_encode(hash('sha256', $code_verifier, true)), '+/', '-_'), '=');
            $url .= '&code_challenge=' . $code_challenge . '&code_challenge_method=S256';
            if ($state) $this->stateStore[$state]['code_verifier'] = $code_verifier;
        }
        $this->triggerEvent('auth_url', compact('provider', 'state', 'usePkce', 'url'));
        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'onAuthUrl'])) $plugin->onAuthUrl($provider, $state, $usePkce, $url, $this);
        }
        return $url;
    }

    public function handleCallback($provider, $code, $state = null) {
        $this->metrics['callbacks']++;
        if (!isset($this->providers[$provider])) {
            $this->metrics['errors']++;
            $this->triggerEvent('error', compact('provider', 'code', 'state'));
            foreach ($this->plugins as $plugin) {
                if (is_callable([$plugin, 'onError'])) $plugin->onError($provider, $code, $state, $this);
            }
            return [ 'error' => 'Unknown provider' ];
        }
        if ($state && (!isset($this->stateStore[$state]) || $this->stateStore[$state]['provider'] !== $provider)) {
            $this->metrics['errors']++;
            $this->triggerEvent('error', compact('provider', 'code', 'state'));
            foreach ($this->plugins as $plugin) {
                if (is_callable([$plugin, 'onError'])) $plugin->onError($provider, $code, $state, $this);
            }
            return [ 'error' => 'Invalid or expired state' ];
        }
        $conf = $this->providers[$provider];
        // Simulate secure token exchange (real implementation would use curl/guzzle)
        $token = 'simulated_token_' . md5($code . $provider . ($state ?? ''));
        $meta = [
            'provider' => $provider,
            'code' => $code,
            'state' => $state,
            'token' => $token
        ];
        if ($this->auditTrail) {
            $this->auditTrail->log('oauth2_callback', [], null, $meta);
        }
        foreach ($this->hooks as $hookFn) {
            call_user_func($hookFn, $provider, $code, $state, $token);
        }
        $this->triggerEvent('callback', compact('provider', 'code', 'state', 'token'));
        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'onCallback'])) $plugin->onCallback($provider, $code, $state, $token, $this);
        }
        return [ 'token' => $token, 'provider' => $provider ];
    }
    // Token refresh (stub)
    public function refreshToken($provider, $refreshToken) {
        $this->metrics['refreshes']++;
        $this->triggerEvent('refresh', compact('provider', 'refreshToken'));
        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'onRefresh'])) $plugin->onRefresh($provider, $refreshToken, $this);
        }
        // Simulate refresh
        return [ 'access_token' => 'refreshed_' . md5($refreshToken . $provider) ];
    }

    // Userinfo (stub)
    public function getUserInfo($provider, $accessToken) {
        $this->metrics['userinfo']++;
        $this->triggerEvent('userinfo', compact('provider', 'accessToken'));
        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'onUserInfo'])) $plugin->onUserInfo($provider, $accessToken, $this);
        }
        // Simulate userinfo
        return [ 'id' => 'user_' . md5($accessToken . $provider), 'provider' => $provider ];
    }

    // Revoke (stub)
    public function revokeToken($provider, $accessToken) {
        $this->triggerEvent('revoke', compact('provider', 'accessToken'));
        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'onRevoke'])) $plugin->onRevoke($provider, $accessToken, $this);
        }
        // Simulate revoke
        return true;
    }

    // Provider discovery (stub)
    public function discover($url) {
        $this->triggerEvent('discover', compact('url'));
        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'onDiscover'])) $plugin->onDiscover($url, $this);
        }
        // Simulate discovery
        return [ 'issuer' => $url, 'authorization_endpoint' => $url . '/auth', 'token_endpoint' => $url . '/token' ];
    }

    // Plugin system
    public function registerPlugin($plugin) { $this->plugins[] = $plugin; }
    // Event system
    public function on($event, callable $cb) { $this->events[$event][] = $cb; }
    protected function triggerEvent($event, $data = null) {
        foreach ($this->events[$event] ?? [] as $cb) call_user_func($cb, $data, $this);
    }
    // Metrics
    public function getMetrics() { return $this->metrics; }
    // Context
    public function setContext($key, $value) { $this->context[$key] = $value; }
    public function getContext($key, $default = null) { return $this->context[$key] ?? $default; }
    // Introspection
    public function getPlugins() { return $this->plugins; }
    public function getEvents() { return $this->events; }

    public function getProviders() {
        return $this->providers;
    }
}
