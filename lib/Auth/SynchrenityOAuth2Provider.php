<?php
namespace Synchrenity\Auth;


class SynchrenityOAuth2Provider {
    protected $providers = [];
    protected $auditTrail;
    protected $hooks = [];
    protected $stateStore = [];

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
    }

    public function getAuthUrl($provider, $state = null, $usePkce = false) {
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
            $this->stateStore[$state]['code_verifier'] = $code_verifier;
        }
        return $url;
    }

    public function handleCallback($provider, $code, $state = null) {
        if (!isset($this->providers[$provider])) return [ 'error' => 'Unknown provider' ];
        if ($state && (!isset($this->stateStore[$state]) || $this->stateStore[$state]['provider'] !== $provider)) {
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
        return [ 'token' => $token, 'provider' => $provider ];
    }

    public function getProviders() {
        return $this->providers;
    }
}
