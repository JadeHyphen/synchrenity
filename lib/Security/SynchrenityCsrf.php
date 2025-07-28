<?php
namespace Synchrenity\Security;


class SynchrenityCsrf {
    protected $sessionKey = '_synchrenity_csrf';

    public function generateToken() {
        if (empty($_SESSION[$this->sessionKey])) {
            $_SESSION[$this->sessionKey] = bin2hex(random_bytes(32));
        }
        return $_SESSION[$this->sessionKey];
    }

    public function getToken() {
        return $_SESSION[$this->sessionKey] ?? $this->generateToken();
    }

    public function validate($token) {
        return hash_equals($this->getToken(), $token);
    }

    public function inputField() {
        $token = htmlspecialchars($this->getToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_csrf" value="' . $token . '">';
    }
}
