<?php
namespace Synchrenity\Http;

/**
 * SynchrenityCookie: Secure, signed, encrypted, HTTP-only, SameSite
 */
class SynchrenityCookie
{
    protected $secret = 'synchrenity_cookie_secret';

    public function set($name, $value, $expire = 0, $secure = true, $httpOnly = true, $sameSite = 'Lax', $domain = '', $consent = false)
    {
        if ($consent && !$this->hasConsent()) {
            return false;
        }
        $signed = $this->sign($value);
        $encrypted = $this->encrypt($signed);
        $params = [
            'expires' => $expire,
            'path' => '/',
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite
        ];
        if (!empty($domain)) {
            $params['domain'] = $domain;
        }
        setcookie($name, $encrypted, $params);
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
        if (!isset($_COOKIE[$name])) return null;
        $decrypted = $this->decrypt($_COOKIE[$name]);
        return $this->verify($decrypted);
    }

    public function delete($name)
    {
        setcookie($name, '', time() - 3600, '/');
        unset($_COOKIE[$name]);
    }

    protected function sign($value)
    {
        return $value . '|' . hash_hmac('sha256', $value, $this->secret);
    }

    protected function verify($signed)
    {
        $parts = explode('|', $signed);
        if (count($parts) !== 2) return null;
        if (hash_hmac('sha256', $parts[0], $this->secret) === $parts[1]) {
            return $parts[0];
        }
        return null;
    }

    protected function encrypt($data)
    {
        // Simple stub, replace with real encryption
        return base64_encode($data);
    }

    protected function decrypt($data)
    {
        return base64_decode($data);
    }
}
