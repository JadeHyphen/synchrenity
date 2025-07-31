<?php

declare(strict_types=1);

namespace Synchrenity\Contracts;

/**
 * Cache interface for Synchrenity cache implementations
 */
interface CacheInterface
{
    /**
     * Set a cache value
     */
    public function set(string $key, $value, int $ttl = 3600): void;

    /**
     * Get a cache value
     */
    public function get(string $key);

    /**
     * Delete a cache value
     */
    public function delete(string $key): bool;

    /**
     * Clear all cache
     */
    public function clear(): bool;

    /**
     * Check if key exists
     */
    public function has(string $key): bool;
}