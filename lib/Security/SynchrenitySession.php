<?php

declare(strict_types=1);

namespace Synchrenity\Security;

class SynchrenitySession
{
    protected $backend;
    protected $flashKey = '_synchrenity_flash';
    protected $store;
    protected $redisClient = null;
    protected $dbConnection = null;
    protected $sessionId = null;
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
        return $this->quota > 0 ? strlen(json_encode($this->store)) <= $this->quota : true;
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
            if (isset($options['redis']) && $options['redis'] instanceof \Redis) {
                $this->redisClient = $options['redis'];
                $this->sessionId = $options['session_id'] ?? session_id() ?? uniqid('session_', true);
                $this->store = new \ArrayObject();
                $this->loadFromRedis();
            } else {
                throw new \InvalidArgumentException('Redis backend requires a Redis client instance in options[\'redis\']');
            }
        } elseif ($backend === 'db') {
            if (isset($options['db']) && $options['db'] instanceof \PDO) {
                $this->dbConnection = $options['db'];
                $this->sessionId = $options['session_id'] ?? session_id() ?? uniqid('session_', true);
                $this->store = new \ArrayObject();
                $this->createSessionTable();
                $this->loadFromDatabase();
            } else {
                throw new \InvalidArgumentException('Database backend requires a PDO instance in options[\'db\']');
            }
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
        
        // Persist to backend storage
        $this->persistToBackend();
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
        
        // Persist to backend storage
        $this->persistToBackend();
    }

    public function flush()
    {
        $this->stats['flushes']++;
        $this->trigger('flush');
        $this->notify('flush');

        if ($this->backend === 'file') {
            session_unset();
        } elseif ($this->backend === 'redis') {
            $this->deleteFromRedis();
        } elseif ($this->backend === 'db') {
            $this->deleteFromDatabase();
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
        // Security: Validate file path
        $realpath = realpath(dirname($file));
        if ($realpath === false || strpos($realpath, realpath(sys_get_temp_dir())) !== 0) {
            throw new \InvalidArgumentException('Invalid file path for session export');
        }
        
        $data = json_encode([$this->store, $this->expirations], JSON_THROW_ON_ERROR);
        if (file_put_contents($file, $data) === false) {
            throw new \RuntimeException('Failed to write session export file');
        }
    }
    public function import($file)
    {
        if (file_exists($file)) {
            // Security: Validate file path
            $realpath = realpath($file);
            if ($realpath === false || strpos($realpath, realpath(sys_get_temp_dir())) !== 0) {
                throw new \InvalidArgumentException('Invalid file path for session import');
            }
            
            $contents = file_get_contents($file);
            if ($contents === false) {
                throw new \RuntimeException('Failed to read session import file');
            }
            
            $data = json_decode($contents, true);
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid JSON in session import file: ' . json_last_error_msg());
            }
            
            if (!is_array($data) || count($data) !== 2) {
                throw new \InvalidArgumentException('Invalid session import data format');
            }
            
            list($this->store, $this->expirations) = $data;
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
        $cipher = openssl_encrypt(json_encode($data), 'AES-256-CBC', $this->encryptionKey, 0, $iv);

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
        if ($plain === false) {
            throw new \RuntimeException('Failed to decrypt session data');
        }

        $result = json_decode($plain, true);
        if ($result === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON in session data: ' . json_last_error_msg());
        }
        
        return $result;
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

    // Backend persistence helper
    protected function persistToBackend()
    {
        if ($this->backend === 'redis') {
            $this->saveToRedis();
        } elseif ($this->backend === 'db') {
            $this->saveToDatabase();
        }
        // File backend uses $_SESSION reference, so no explicit save needed
    }

    // Database backend helper methods
    protected function createSessionTable()
    {
        if ($this->dbConnection) {
            $sql = "CREATE TABLE IF NOT EXISTS synchrenity_sessions (
                id VARCHAR(255) PRIMARY KEY,
                data TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NULL
            )";
            try {
                $this->dbConnection->exec($sql);
            } catch (\PDOException $e) {
                // Table might already exist or different SQL dialect
                error_log("Session table creation failed: " . $e->getMessage());
            }
        }
    }

    protected function loadFromDatabase()
    {
        if ($this->dbConnection && $this->sessionId) {
            $stmt = $this->dbConnection->prepare("SELECT data FROM synchrenity_sessions WHERE id = ? AND (expires_at IS NULL OR expires_at > NOW())");
            $stmt->execute([$this->sessionId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && $row['data']) {
                $sessionData = json_decode($row['data'], true);
                if (is_array($sessionData)) {
                    $this->store->exchangeArray($sessionData);
                }
            }
        }
    }

    protected function saveToDatabase()
    {
        if ($this->dbConnection && $this->sessionId) {
            $data = json_encode($this->store->getArrayCopy());
            $expiresAt = $this->idleTimeout > 0 ? date('Y-m-d H:i:s', time() + $this->idleTimeout) : null;
            
            $stmt = $this->dbConnection->prepare("INSERT INTO synchrenity_sessions (id, data, expires_at) VALUES (?, ?, ?) 
                                                 ON DUPLICATE KEY UPDATE data = VALUES(data), expires_at = VALUES(expires_at), updated_at = CURRENT_TIMESTAMP");
            $stmt->execute([$this->sessionId, $data, $expiresAt]);
        }
    }

    protected function deleteFromDatabase()
    {
        if ($this->dbConnection && $this->sessionId) {
            $stmt = $this->dbConnection->prepare("DELETE FROM synchrenity_sessions WHERE id = ?");
            $stmt->execute([$this->sessionId]);
        }
    }

    // Redis backend helper methods
    protected function loadFromRedis()
    {
        if ($this->redisClient && $this->sessionId) {
            $key = 'session:' . $this->sessionId;
            $data = $this->redisClient->get($key);
            if ($data) {
                $sessionData = json_decode($data, true);
                if (is_array($sessionData)) {
                    $this->store->exchangeArray($sessionData);
                }
            }
        }
    }

    protected function saveToRedis()
    {
        if ($this->redisClient && $this->sessionId) {
            $key = 'session:' . $this->sessionId;
            $data = json_encode($this->store->getArrayCopy());
            $ttl = $this->idleTimeout > 0 ? $this->idleTimeout : 3600; // Default 1 hour
            $this->redisClient->setex($key, $ttl, $data);
        }
    }

    protected function deleteFromRedis()
    {
        if ($this->redisClient && $this->sessionId) {
            $key = 'session:' . $this->sessionId;
            $this->redisClient->del($key);
        }
    }
}
