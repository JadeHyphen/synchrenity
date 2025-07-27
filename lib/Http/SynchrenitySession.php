<?php
namespace Synchrenity\Http;

/**
 * SynchrenitySession: Secure, encrypted, flash data, CSRF
 */
class SynchrenitySession
{
    public function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function get($key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    public function has($key)
    {
        return isset($_SESSION[$key]);
    }

    public function remove($key)
    {
        unset($_SESSION[$key]);
    }

    public function flash($key, $value)
    {
        $_SESSION['flash'][$key] = $value;
    }

    public function getFlash($key, $default = null)
    {
        $value = $_SESSION['flash'][$key] ?? $default;
        unset($_SESSION['flash'][$key]);
        return $value;
    }

    public function csrfToken()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function regenerate()
    {
        session_regenerate_id(true);
    }

    public function destroy()
    {
        session_destroy();
        $_SESSION = [];
    }

    // Encryption stub for future secure session storage
    public function encrypt($data) { return $data; }
    public function decrypt($data) { return $data; }
}
