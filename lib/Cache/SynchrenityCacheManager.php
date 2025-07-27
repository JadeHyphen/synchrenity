<?php
namespace Synchrenity\Cache;

class SynchrenityCacheManager {
    protected $auditTrail;
    protected $backend = 'memory'; // memory, file, redis
    protected $cache = [];
    protected $filePath;
    /**
     * Redis instance (uncomment and enable if you have the Redis extension)
     * @var \ Redis|null
     */
    // protected $redis = null;
    protected $redisEnabled = false;

    public function __construct($backend = 'memory', $options = []) {
        $this->backend = $backend;
        if ($backend === 'file') {
            $this->filePath = $options['filePath'] ?? __DIR__ . '/cache.data';
            if (!file_exists($this->filePath)) file_put_contents($this->filePath, json_encode([]));
            $this->loadFileCache();
        } elseif ($backend === 'redis') {
            // To enable Redis, uncomment the redis property above and this block, and ensure the Redis extension is installed.
            // if (class_exists('Redis')) {
            //     $this->redis = new \Redis();
            //     $host = $options['host'] ?? '127.0.0.1';
            //     $port = $options['port'] ?? 6379;
            //     $this->redis->connect($host, $port);
            //     $this->redisEnabled = true;
            // } else {
            //     $this->backend = 'memory';
            //     $this->redisEnabled = false;
            //     if (isset($options['auditTrail'])) {
            //         $this->auditTrail = $options['auditTrail'];
            //     }
            //     if ($this->auditTrail) {
            //         $this->auditTrail->log('cache_fallback', ['reason' => 'Redis extension not available'], null);
            //     }
            // }
            // Default: fallback to memory
            $this->backend = 'memory';
            $this->redisEnabled = false;
            if (isset($options['auditTrail'])) {
                $this->auditTrail = $options['auditTrail'];
            }
            if ($this->auditTrail) {
                $this->auditTrail->log('cache_fallback', ['reason' => 'Redis extension not available'], null);
            }
        }
    }

    public function setAuditTrail($auditTrail) {
        $this->auditTrail = $auditTrail;
    }

    public function set($key, $value, $ttl = 3600) {
        $expires = time() + $ttl;
        if ($this->backend === 'memory') {
            $this->cache[$key] = ['value' => $value, 'expires' => $expires];
        } elseif ($this->backend === 'file') {
            $this->cache[$key] = ['value' => $value, 'expires' => $expires];
            $this->saveFileCache();
        } elseif ($this->backend === 'redis' && $this->redisEnabled) {
            // $this->redis->setex($key, $ttl, serialize($value));
        }
        if ($this->auditTrail) {
            $this->auditTrail->log('set_cache', ['key' => $key, 'value' => $value, 'ttl' => $ttl], null);
        }
    }

    public function get($key) {
        if ($this->backend === 'memory') {
            if (!isset($this->cache[$key])) return null;
            if ($this->cache[$key]['expires'] < time()) {
                unset($this->cache[$key]);
                return null;
            }
            return $this->cache[$key]['value'];
        } elseif ($this->backend === 'file') {
            if (!isset($this->cache[$key])) return null;
            if ($this->cache[$key]['expires'] < time()) {
                unset($this->cache[$key]);
                $this->saveFileCache();
                return null;
            }
            return $this->cache[$key]['value'];
        } elseif ($this->backend === 'redis' && $this->redisEnabled) {
            // $val = $this->redis->get($key);
            // return $val ? unserialize($val) : null;
        }
        return null;
    }

    public function delete($key) {
        if ($this->backend === 'memory') {
            unset($this->cache[$key]);
        } elseif ($this->backend === 'file') {
            unset($this->cache[$key]);
            $this->saveFileCache();
        } elseif ($this->backend === 'redis' && $this->redisEnabled) {
            // $this->redis->del($key);
        }
        if ($this->auditTrail) {
            $this->auditTrail->log('delete_cache', ['key' => $key], null);
        }
    }

    public function exists($key) {
        if ($this->backend === 'memory' || $this->backend === 'file') {
            return isset($this->cache[$key]) && $this->cache[$key]['expires'] >= time();
        } elseif ($this->backend === 'redis' && $this->redisEnabled) {
            // return $this->redis->exists($key);
        }
        return false;
    }

    public function clear() {
        if ($this->backend === 'memory') {
            $this->cache = [];
        } elseif ($this->backend === 'file') {
            $this->cache = [];
            $this->saveFileCache();
        } elseif ($this->backend === 'redis' && $this->redisEnabled) {
            // $this->redis->flushDB();
        }
        if ($this->auditTrail) {
            $this->auditTrail->log('clear_cache', [], null);
        }
    }

    // Internal: load/save file cache
    protected function loadFileCache() {
        $data = @file_get_contents($this->filePath);
        $this->cache = $data ? json_decode($data, true) : [];
    }
    protected function saveFileCache() {
        file_put_contents($this->filePath, json_encode($this->cache));
    }
}
