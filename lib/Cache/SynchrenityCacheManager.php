<?php

declare(strict_types=1);

namespace Synchrenity\Cache;

class SynchrenityCacheManager
{
    protected $auditTrail;
    protected string $backend = 'memory'; // memory, file, redis
    protected array $cache   = [];
    protected ?string $filePath = null;
    protected ?\Redis $redis = null;
    protected bool $redisEnabled = false;

    public function __construct(string $backend = 'memory', array $options = [])
    {
        $this->backend = $backend;

        if ($backend === 'file') {
            $this->filePath = $options['filePath'] ?? __DIR__ . '/cache.data';

            if (!file_exists($this->filePath)) {
                $dir = dirname($this->filePath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                file_put_contents($this->filePath, json_encode([]));
            }
            $this->loadFileCache();
        } elseif ($backend === 'redis') {
            if (class_exists('Redis') && extension_loaded('redis')) {
                $this->redis = new \Redis();
                $host = $options['host'] ?? '127.0.0.1';
                $port = $options['port'] ?? 6379;
                
                try {
                    if ($this->redis->connect($host, $port)) {
                        $this->redisEnabled = true;
                    } else {
                        throw new \RuntimeException("Failed to connect to Redis server at $host:$port");
                    }
                } catch (\Throwable $e) {
                    $this->backend = 'memory';
                    $this->redisEnabled = false;
                    if (isset($options['auditTrail'])) {
                        $this->auditTrail = $options['auditTrail'];
                    }
                    if ($this->auditTrail) {
                        $this->auditTrail->log('cache_fallback', ['reason' => 'Redis connection failed: ' . $e->getMessage()], null);
                    }
                }
            } else {
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

        if (isset($options['auditTrail'])) {
            $this->auditTrail = $options['auditTrail'];
        }
    }

    public function setAuditTrail($auditTrail): void
    {
        $this->auditTrail = $auditTrail;
    }

    public function set(string $key, $value, int $ttl = 3600): void
    {
        if (empty($key)) {
            throw new \InvalidArgumentException('Cache key cannot be empty');
        }

        $expires = time() + $ttl;

        try {
            if ($this->backend === 'memory') {
                $this->cache[$key] = ['value' => $value, 'expires' => $expires];
            } elseif ($this->backend === 'file') {
                $this->cache[$key] = ['value' => $value, 'expires' => $expires];
                $this->saveFileCache();
            } elseif ($this->backend === 'redis' && $this->redisEnabled && $this->redis) {
                $this->redis->setex($key, $ttl, serialize($value));
            }

            if ($this->auditTrail) {
                $this->auditTrail->log('set_cache', ['key' => $key, 'ttl' => $ttl], null);
            }
        } catch (\Throwable $e) {
            if ($this->auditTrail) {
                $this->auditTrail->log('cache_error', ['action' => 'set', 'key' => $key, 'error' => $e->getMessage()], null);
            }
            throw new \RuntimeException("Failed to set cache key '$key': " . $e->getMessage(), 0, $e);
        }
    }

    public function get(string $key)
    {
        if (empty($key)) {
            throw new \InvalidArgumentException('Cache key cannot be empty');
        }

        try {
            if ($this->backend === 'memory') {
                if (!isset($this->cache[$key])) {
                    return null;
                }

                if ($this->cache[$key]['expires'] < time()) {
                    unset($this->cache[$key]);
                    return null;
                }

                return $this->cache[$key]['value'];
            } elseif ($this->backend === 'file') {
                if (!isset($this->cache[$key])) {
                    return null;
                }

                if ($this->cache[$key]['expires'] < time()) {
                    unset($this->cache[$key]);
                    $this->saveFileCache();
                    return null;
                }

                return $this->cache[$key]['value'];
            } elseif ($this->backend === 'redis' && $this->redisEnabled && $this->redis) {
                $value = $this->redis->get($key);
                if ($value === false) {
                    return null;
                }
                return unserialize($value);
            }

            return null;
        } catch (\Throwable $e) {
            if ($this->auditTrail) {
                $this->auditTrail->log('cache_error', ['action' => 'get', 'key' => $key, 'error' => $e->getMessage()], null);
            }
            return null; // Graceful degradation
        }
    }

    public function delete($key)
    {
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

    public function exists($key)
    {
        if ($this->backend === 'memory' || $this->backend === 'file') {
            return isset($this->cache[$key]) && $this->cache[$key]['expires'] >= time();
        } elseif ($this->backend === 'redis' && $this->redisEnabled) {
            // return $this->redis->exists($key);
        }

        return false;
    }

    public function clear()
    {
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
    protected function loadFileCache()
    {
        $data        = @file_get_contents($this->filePath);
        $this->cache = $data ? json_decode($data, true) : [];
    }
    protected function saveFileCache()
    {
        file_put_contents($this->filePath, json_encode($this->cache));
    }
}
