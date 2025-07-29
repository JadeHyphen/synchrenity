<?php

declare(strict_types=1);

namespace Synchrenity\Http;

/**
 * SynchrenityCookie: Secure, signed, encrypted, HTTP-only, SameSite
 */
class SynchrenityCookie
{
    protected $secret        = 'synchrenity_cookie_secret';
    protected $encryptionKey = 'synchrenity_cookie_enc_key';
    protected $plugins       = [];
    protected $events        = [];
    protected $metrics       = [
        'set'     => 0,
        'get'     => 0,
        'delete'  => 0,
        'consent' => 0,
    ];
    protected $context = [];

    public function set($name, $value, $expire = 0, $secure = true, $httpOnly = true, $sameSite = 'Lax', $domain = '', $consent = false)
    {
        if ($consent && !$this->hasConsent()) {
            return false;
        }
        $this->metrics['set']++;
        $signed    = $this->sign($value);
        $encrypted = $this->encrypt($signed);
        $params    = [
            'expires'  => $expire,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ];

        if (!empty($domain)) {
            $params['domain'] = $domain;
        }
        setcookie($name, $encrypted, $params);
        $this->triggerEvent('set', $name);

        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'onSet'])) {
                $plugin->onSet($name, $value, $this);
            }
        }

        return true;
    }

    public function setPersistentLogin($userId, $token, $expire = 2592000)
    {
        $data = json_encode(['user_id' => $userId, 'token' => $token]);

        return $this->set('persistent_login', $data, time() + $expire, true, true, 'Lax');
    }

    public function getPersistentLogin()
    {
        $data = $this->get('persistent_login');

        if ($data) {
            return json_decode($data, true);
        }

        return null;
    }

    public function setConsent($value = true)
    {
        $this->set('cookie_consent', $value ? '1' : '0', time() + 31536000, true, true, 'Strict');
    }

    public function hasConsent()
    {
        return $this->get('cookie_consent') === '1';
    }

    public function setCORSHeaders($origin = '*', $methods = 'GET,POST,PUT,DELETE', $headers = 'Content-Type,Authorization')
    {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: ' . $methods);
        header('Access-Control-Allow-Headers: ' . $headers);
        header('Access-Control-Allow-Credentials: true');
    }

    // Secure session storage stub (for DB/Redis integration)
    public function storeSession($sessionId, $data)
    {
        // Implement DB/Redis storage here
        return true;
    }

    public function retrieveSession($sessionId)
    {
        // Implement DB/Redis retrieval here
        return null;
    }

    public function get($name)
    {
        $this->metrics['get']++;

        if (!isset($_COOKIE[$name])) {
            return null;
        }
        $decrypted = $this->decrypt($_COOKIE[$name]);
        $verified  = $this->verify($decrypted);
        $this->triggerEvent('get', $name);

        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'onGet'])) {
                $verified = $plugin->onGet($name, $verified, $this);
            }
        }

        return $verified;
    }

    public function delete($name)
    {
        $this->metrics['delete']++;
        setcookie($name, '', time() - 3600, '/');
        unset($_COOKIE[$name]);
        $this->triggerEvent('delete', $name);

        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'onDelete'])) {
                $plugin->onDelete($name, $this);
            }
        }
    }

    protected function sign($value)
    {
        return $value . '|' . hash_hmac('sha256', $value, $this->secret);
    }

    protected function verify($signed)
    {
        $parts = explode('|', $signed);

        if (count($parts) !== 2) {
            return null;
        }

        if (hash_hmac('sha256', $parts[0], $this->secret) === $parts[1]) {
            return $parts[0];
        }

        return null;
    }

    protected function encrypt($data)
    {
        if (!$this->encryptionKey) {
            return base64_encode($data);
        }
        $iv = substr(hash('sha256', $this->encryptionKey), 0, 16);

        return base64_encode(openssl_encrypt($data, 'aes-256-cbc', $this->encryptionKey, 0, $iv));
    }

    protected function decrypt($data)
    {
        if (!$this->encryptionKey) {
            return base64_decode($data);
        }
        $iv  = substr(hash('sha256', $this->encryptionKey), 0, 16);
        $dec = openssl_decrypt(base64_decode($data), 'aes-256-cbc', $this->encryptionKey, 0, $iv);

        return $dec === false ? null : $dec;
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
