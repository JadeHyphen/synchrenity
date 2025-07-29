<?php

declare(strict_types=1);

namespace Synchrenity\Http;

/**
 * SynchrenitySession: Secure, encrypted, flash data, CSRF
 */
class SynchrenitySession
{
    protected $encryptionKey = null;
    protected $plugins       = [];
    protected $events        = [];
    protected $metrics       = [
        'starts'      => 0,
        'gets'        => 0,
        'sets'        => 0,
        'flashes'     => 0,
        'csrf'        => 0,
        'regenerates' => 0,
        'destroys'    => 0,
    ];
    protected $meta = [];

    public function __construct($encryptionKey = null)
    {
        $this->encryptionKey = $encryptionKey;
    }

    public function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
            $this->metrics['starts']++;
            $this->triggerEvent('start');
        }
    }

    public function get($key, $default = null)
    {
        $this->metrics['gets']++;
        $this->triggerEvent('get', $key);
        $val = $_SESSION[$key] ?? $default;

        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'onGet'])) {
                $val = $plugin->onGet($key, $val, $this);
            }
        }

        return $this->decrypt($val);
    }

    public function set($key, $value)
    {
        $this->metrics['sets']++;
        $this->triggerEvent('set', $key);
        $val = $this->encrypt($value);

        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'onSet'])) {
                $val = $plugin->onSet($key, $val, $this);
            }
        }
        $_SESSION[$key] = $val;
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
        $this->metrics['flashes']++;
        $this->triggerEvent('flash', $key);
        $_SESSION['flash'][$key] = $this->encrypt($value);
    }

    public function getFlash($key, $default = null)
    {
        $val = $_SESSION['flash'][$key] ?? $default;
        unset($_SESSION['flash'][$key]);
        $this->triggerEvent('getFlash', $key);

        return $this->decrypt($val);
    }

    public function csrfToken($rotate = false)
    {
        $this->metrics['csrf']++;

        if ($rotate || empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $this->triggerEvent('csrf');

        return $_SESSION['csrf_token'];
    }

    public function regenerate($deleteOld = true)
    {
        session_regenerate_id($deleteOld);
        $this->metrics['regenerates']++;
        $this->triggerEvent('regenerate');
    }

    public function destroy()
    {
        session_destroy();
        $_SESSION = [];
        $this->metrics['destroys']++;
        $this->triggerEvent('destroy');
    }

    // Encryption (AES-256-CBC, base64)
    public function encrypt($data)
    {
        if (!$this->encryptionKey || $data === null) {
            return $data;
        }
        $iv = substr(hash('sha256', $this->encryptionKey), 0, 16);

        return base64_encode(openssl_encrypt(serialize($data), 'aes-256-cbc', $this->encryptionKey, 0, $iv));
    }
    public function decrypt($data)
    {
        if (!$this->encryptionKey || $data === null) {
            return $data;
        }
        $iv  = substr(hash('sha256', $this->encryptionKey), 0, 16);
        $dec = openssl_decrypt(base64_decode($data), 'aes-256-cbc', $this->encryptionKey, 0, $iv);

        return $dec === false ? $data : unserialize($dec);
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

    // Metadata
    public function setMeta($key, $value)
    {
        $this->meta[$key] = $value;
    }
    public function getMeta($key, $default = null)
    {
        return $this->meta[$key] ?? $default;
    }

    // Metrics
    public function getMetrics()
    {
        return $this->metrics;
    }

    // Validation
    public function isValid()
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }
}
