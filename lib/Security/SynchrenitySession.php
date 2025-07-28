<?php
namespace Synchrenity\Security;


class SynchrenitySession {
    protected $backend;
    protected $flashKey = '_synchrenity_flash';

    public function __construct($backend = 'file') {
        $this->backend = $backend;
        if ($backend === 'file') {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
        }
        // TODO: Add Redis/DB backend support
    }

    public function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }

    public function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    public function forget($key) {
        unset($_SESSION[$key]);
    }

    public function flush() {
        session_unset();
    }

    public function regenerate() {
        session_regenerate_id(true);
    }

    // Flash messages (one-time)
    public function flash($key, $value) {
        $_SESSION[$this->flashKey][$key] = $value;
    }
    public function getFlash($key, $default = null) {
        $val = $_SESSION[$this->flashKey][$key] ?? $default;
        unset($_SESSION[$this->flashKey][$key]);
        return $val;
    }
    public function allFlash() {
        $flashes = $_SESSION[$this->flashKey] ?? [];
        unset($_SESSION[$this->flashKey]);
        return $flashes;
    }

    // Harden session cookie
    public static function hardenCookie() {
        if (PHP_SESSION_ACTIVE === session_status()) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }
}
