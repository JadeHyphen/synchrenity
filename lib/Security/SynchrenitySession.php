<?php

declare(strict_types=1);

namespace Synchrenity\Security;

class SynchrenitySession
{
    protected $backend;
    protected $flashKey = '_synchrenity_flash';
    protected $store;
    protected $metaKey         = '_synchrenity_meta';
    protected $encryptionKey   = null;
    protected $idleTimeout     = 0;
    protected $lastActivityKey = '_synchrenity_last_activity';
    protected $locks           = [];
    protected $expirations     = [];
    protected $eventHooks      = [
        'set'        => [],
        'get'        => [],
        'forget'     => [],
        'flush'      => [],
        'regenerate' => [],
    ];
    protected $snapshots = [];
    protected $observers = [];
    protected $stats     = [ 'sets' => 0, 'gets' => 0, 'forgets' => 0, 'flushes' => 0, 'regenerates' => 0 ];
    // Session event hooks
    public function on($event, $cb)
    {
        if (isset($this->eventHooks[$event])) {
            $this->eventHooks[$event][] = $cb;
        }
    }
    protected function trigger($event, ...$args)
    {
        if (isset($this->eventHooks[$event])) {
            foreach ($this->eventHooks[$event] as $cb) {
                call_user_func_array($cb, $args);
            }
        }
    }
    // Session observer
    public function observe($cb)
    {
        $this->observers[] = $cb;
    }
    protected function notify($action, $key = null, $value = null)
    {
        foreach ($this->observers as $cb) {
            call_user_func($cb, $action, $key, $value);
        }
    }

    // Session rolling (refresh expiration on access)
    protected $rolling = false;
    public function enableRolling($on = true)
    {
        $this->rolling = $on;
    }

    // Per-key access control
    protected $keyAccessControl = null;
    public function setKeyAccessControl($cb)
    {
        $this->keyAccessControl = $cb;
    }

    // Session audit trail
    protected $auditTrail = [];
    public function auditTrail()
    {
        return $this->auditTrail;
    }

    // Session garbage collection
    public function gc()
    {
        foreach ($this->expirations as $key => $exp) {
            if (time() > $exp) {
                $this->forget($key);
            }
        }
    }

    // Session forking/merge
    public function fork()
    {
        return clone $this;
    }
    public function merge(SynchrenitySession $other)
    {
        foreach ($other->store as $k => $v) {
            $this->store[$k] = $v;
        }

        foreach ($other->expirations as $k => $v) {
            $this->expirations[$k] = $v;
        }
    }

    // Session event replay
    public function replayEvents($events)
    {
        foreach ($events as $e) {
            $this->trigger($e['event'], ...($e['args'] ?? []));
        }
    }

    // Session key namespacing
    protected $namespace = '';
    public function setNamespace($ns)
    {
        $this->namespace = $ns;
    }
    protected function ns($key)
    {
        return $this->namespace ? $this->namespace . ':' . $key : $key;
    }

    // Session quota/limits
    protected $quota = 0;
    public function setQuota($bytes)
    {
        $this->quota = $bytes;
    }
    public function checkQuota()
    {
        return $this->quota > 0 ? strlen(serialize($this->store)) <= $this->quota : true;
    }

    // Session health check
    public function healthCheck()
    {
        return is_array($this->store) || $this->store instanceof \ArrayAccess;
    }

    public function __construct($backend = 'file', $options = [])
    {
        $this->backend = $backend;

        if ($backend === 'file') {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $this->store = &$_SESSION;
        } elseif ($backend === 'redis') {
            $this->store = new \ArrayObject();
            // TODO: Implement Redis logic (use $options['redis'] for client)
        } elseif ($backend === 'db') {
            $this->store = new \ArrayObject();
            // TODO: Implement DB logic (use $options['db'] for PDO/connection)
        }

        if (isset($options['encryption_key'])) {
            $this->encryptionKey = $options['encryption_key'];
        }

        if (isset($options['idle_timeout'])) {
            $this->idleTimeout = (int)$options['idle_timeout'];
        }
        $this->checkIdleTimeout();
        $this->fingerprint();
    }

    public function get($key, $default = null)
    {
        $key = $this->ns($key);
        $this->stats['gets']++;
        $this->trigger('get', $key);
        $this->notify('get', $key, null);

        if ($this->keyAccessControl && !call_user_func($this->keyAccessControl, 'get', $key)) {
            return $default;
        }

        if (isset($this->expirations[$key]) && time() > $this->expirations[$key]) {
            $this->forget($key);

            return $default;
        }

        if ($this->rolling && isset($this->expirations[$key])) {
            $this->expirations[$key] = time() + ($this->expirations[$key] - time());
        }
        $val = $this->store[$key] ?? $default;

        if ($this->encryptionKey && $val !== null) {
            $val = $this->decrypt($val);
        }
        $this->auditTrail[] = ['event' => 'get','key' => $key,'time' => time()];

        return $val;
    }

    public function set($key, $value, $ttl = null)
    {
        $key = $this->ns($key);
        $this->stats['sets']++;
        $this->trigger('set', $key, $value);
        $this->notify('set', $key, $value);

        if ($this->keyAccessControl && !call_user_func($this->keyAccessControl, 'set', $key)) {
            return;
        }

        if ($this->quota > 0 && ! $this->checkQuota()) {
            return;
        }

        if ($this->encryptionKey) {
            $value = $this->encrypt($value);
        }
        $this->store[$key] = $value;

        if ($ttl) {
            $this->expirations[$key] = time() + $ttl;
        }
        $this->auditTrail[] = ['event' => 'set','key' => $key,'value' => $value,'time' => time()];
    }

    public function forget($key)
    {
        $key = $this->ns($key);
        $this->stats['forgets']++;
        $this->trigger('forget', $key);
        $this->notify('forget', $key, null);

        if ($this->keyAccessControl && !call_user_func($this->keyAccessControl, 'forget', $key)) {
            return;
        }
        unset($this->store[$key]);
        unset($this->expirations[$key]);
        $this->auditTrail[] = ['event' => 'forget','key' => $key,'time' => time()];
    }

    public function flush()
    {
        $this->stats['flushes']++;
        $this->trigger('flush');
        $this->notify('flush');

        if ($this->backend === 'file') {
            session_unset();
        }
        $this->store       = [];
        $this->expirations = [];
    }

    public function regenerate()
    {
        $this->stats['regenerates']++;
        $this->trigger('regenerate');
        $this->notify('regenerate');

        if ($this->backend === 'file') {
            session_regenerate_id(true);
        }
        $this->fingerprint();
    }
    // Session locking
    public function lock($key)
    {
        $this->locks[$key] = true;
    }
    public function unlock($key)
    {
        unset($this->locks[$key]);
    }
    public function isLocked($key)
    {
        return !empty($this->locks[$key]);
    }

    // Session snapshot/restore
    public function snapshot($name = 'default')
    {
        $this->snapshots[$name] = [ 'store' => $this->store, 'expirations' => $this->expirations ];
    }
    public function restore($name = 'default')
    {
        if (isset($this->snapshots[$name])) {
            $this->store       = $this->snapshots[$name]['store'];
            $this->expirations = $this->snapshots[$name]['expirations'];
        }
    }

    // Session stats
    public function stats()
    {
        return $this->stats;
    }

    // Session import/export
    public function export($file)
    {
        file_put_contents($file, serialize([$this->store, $this->expirations]));
    }
    public function import($file)
    {
        if (file_exists($file)) {
            list($this->store, $this->expirations) = unserialize(file_get_contents($file));
        }
    }

    // Flash messages (one-time)
    public function flash($key, $value)
    {
        $this->store[$this->flashKey][$key] = $value;
    }
    public function getFlash($key, $default = null)
    {
        $val = $this->store[$this->flashKey][$key] ?? $default;
        unset($this->store[$this->flashKey][$key]);

        return $val;
    }
    public function allFlash()
    {
        $flashes = $this->store[$this->flashKey] ?? [];
        unset($this->store[$this->flashKey]);

        return $flashes;
    }

    // CSRF token
    public function csrfToken()
    {
        if (empty($this->store['_csrf_token'])) {
            $this->store['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $this->store['_csrf_token'];
    }
    public function checkCsrf($token)
    {
        return hash_equals($this->store['_csrf_token'] ?? '', $token);
    }

    // Session fingerprinting (user agent + IP)
    protected function fingerprint()
    {
        $ua                          = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip                          = $_SERVER['REMOTE_ADDR']     ?? '';
        $fp                          = hash('sha256', $ua . '|' . $ip);
        $this->store['_fingerprint'] = $fp;
    }
    public function checkFingerprint()
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR']     ?? '';
        $fp = hash('sha256', $ua . '|' . $ip);

        return ($this->store['_fingerprint'] ?? null) === $fp;
    }

    // Idle timeout
    protected function checkIdleTimeout()
    {
        if ($this->idleTimeout > 0) {
            $now  = time();
            $last = $this->store[$this->lastActivityKey] ?? $now;

            if ($now - $last > $this->idleTimeout) {
                $this->flush();
            }
            $this->store[$this->lastActivityKey] = $now;
        }
    }

    // Encryption helpers (simple, for demo)
    protected function encrypt($data)
    {
        if (!$this->encryptionKey) {
            return $data;
        }
        $iv     = random_bytes(16);
        $cipher = openssl_encrypt(serialize($data), 'AES-256-CBC', $this->encryptionKey, 0, $iv);

        return base64_encode($iv . $cipher);
    }
    protected function decrypt($data)
    {
        if (!$this->encryptionKey) {
            return $data;
        }
        $raw    = base64_decode($data);
        $iv     = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $plain  = openssl_decrypt($cipher, 'AES-256-CBC', $this->encryptionKey, 0, $iv);

        return unserialize($plain);
    }

    // Session metadata
    public function setMeta($key, $value)
    {
        $this->store[$this->metaKey][$key] = $value;
    }
    public function getMeta($key, $default = null)
    {
        return $this->store[$this->metaKey][$key] ?? $default;
    }
    public function allMeta()
    {
        return $this->store[$this->metaKey] ?? [];
    }

    // API output
    public function toJson()
    {
        return json_encode($this->store, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    // Test utilities
    public function fake($data = [])
    {
        $this->store = $data;
    }

    // Harden session cookie
    public static function hardenCookie()
    {
        if (PHP_SESSION_ACTIVE === session_status()) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }
}
