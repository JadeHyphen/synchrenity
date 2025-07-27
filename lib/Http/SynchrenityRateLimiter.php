<?php
namespace Synchrenity\Http;

/**
 * SynchrenityRateLimiter: Configurable, IP/user-based, burst/sliding window
 */
class SynchrenityRateLimiter
{
    protected $limits = [];
    protected $storage = [];

    public function setLimit($key, $maxRequests, $windowSeconds)
    {
        $this->limits[$key] = ['max' => $maxRequests, 'window' => $windowSeconds];
    }

    public function check($key)
    {
        $now = time();
        if (!isset($this->storage[$key])) {
            $this->storage[$key] = [];
        }
        // Remove expired requests
        $this->storage[$key] = array_filter($this->storage[$key], function($ts) use ($now, $key) {
            return $ts > $now - $this->limits[$key]['window'];
        });
        if (count($this->storage[$key]) >= $this->limits[$key]['max']) {
            return false;
        }
        $this->storage[$key][] = $now;
        return true;
    }
}
