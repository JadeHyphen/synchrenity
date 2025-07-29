<?php
namespace Synchrenity\Middleware;

/**
 * Robust, distributed rate limiting middleware with burst, sliding window, and pluggable storage.
 */
class SynchrenityRateLimitMiddleware implements SynchrenityMiddlewareInterface {
    protected $limit;
    protected $window;
    protected $burst;
    protected $storage;
    protected $context = [];
    protected $hooks = [];
    protected $strategy = 'sliding'; // 'fixed', 'sliding', 'token_bucket'

    /**
     * @param int $limit Requests per window
     * @param int $window Window in seconds
     * @param mixed $storage Pluggable storage (array, Redis, etc)
     * @param int $burst Burst capacity (for token bucket)
     * @param string $strategy Rate limit strategy
     */
    public function __construct($limit = 60, $window = 60, $storage = null, $burst = null, $strategy = 'sliding') {
        $this->limit = $limit;
        $this->window = $window;
        $this->burst = $burst ?? $limit;
        $this->storage = $storage ?: [];
        $this->strategy = $strategy;
    }

    public function setContext(array $context) { $this->context = $context; }
    public function before($request) { $this->triggerHook('before', $request); }
    public function after($request, $response) { $this->triggerHook('after', $request, $response); }
    public function onError($request, $exception) { $this->triggerHook('error', $request, $exception); return null; }

    public function addHook($event, callable $cb) { $this->hooks[$event][] = $cb; }
    protected function triggerHook($event, ...$args) { foreach ($this->hooks[$event] ?? [] as $cb) call_user_func_array($cb, $args); }

    public function handle($request, callable $next) {
        $this->before($request);
        $key = $this->resolveKey($request);
        $now = time();
        $allowed = $this->checkRateLimit($key, $now);
        if (!$allowed) {
            $this->triggerHook('rate_limit_exceeded', $request);
            return $this->rateLimitExceededResponse($request);
        }
        $this->recordRequest($key, $now);
        $response = $next($request);
        $this->after($request, $response);
        return $response;
    }

    protected function resolveKey($request) {
        // Use IP, user ID, or custom key
        if (isset($request['user_id'])) return 'user:' . $request['user_id'];
        if (isset($request['ip'])) return 'ip:' . $request['ip'];
        return 'global';
    }

    protected function checkRateLimit($key, $now) {
        switch ($this->strategy) {
            case 'fixed':
                return $this->fixedWindow($key, $now);
            case 'token_bucket':
                return $this->tokenBucket($key, $now);
            case 'sliding':
            default:
                return $this->slidingWindow($key, $now);
        }
    }

    protected function fixedWindow($key, $now) {
        $bucket = $this->getBucket($key, $now);
        return $bucket['count'] < $this->limit;
    }

    protected function slidingWindow($key, $now) {
        $bucket = $this->getBucket($key, $now);
        if (!isset($bucket['requests'])) $bucket['requests'] = [];
        $bucket['requests'] = array_filter($bucket['requests'], function($t) use ($now) { return $t > $now - $this->window; });
        $allowed = count($bucket['requests']) < $this->limit;
        $this->storage[$key]['requests'] = $bucket['requests'];
        return $allowed;
    }

    protected function tokenBucket($key, $now) {
        $bucket = $this->getBucket($key, $now);
        $tokens = $bucket['tokens'] ?? $this->burst;
        $last = $bucket['last'] ?? $now;
        $rate = $this->limit / $this->window;
        $tokens = min($this->burst, $tokens + ($now - $last) * $rate);
        if ($tokens < 1) return false;
        $tokens--;
        $this->storage[$key]['tokens'] = $tokens;
        $this->storage[$key]['last'] = $now;
        return true;
    }

    protected function getBucket($key, $now) {
        if (!isset($this->storage[$key])) {
            $this->storage[$key] = ['count'=>0,'start'=>$now,'requests'=>[],'tokens'=>$this->burst,'last'=>$now];
        }
        $bucket = $this->storage[$key];
        if ($now - $bucket['start'] > $this->window) {
            $bucket = ['count'=>0,'start'=>$now,'requests'=>[],'tokens'=>$this->burst,'last'=>$now];
            $this->storage[$key] = $bucket;
        }
        return $bucket;
    }

    protected function recordRequest($key, $now) {
        switch ($this->strategy) {
            case 'fixed':
                $this->storage[$key]['count'] = ($this->storage[$key]['count'] ?? 0) + 1;
                break;
            case 'token_bucket':
                // Already handled in tokenBucket()
                break;
            case 'sliding':
            default:
                $this->storage[$key]['requests'][] = $now;
                break;
        }
    }

    protected function rateLimitExceededResponse($request) {
        return ['error'=>'Rate limit exceeded','status'=>429,'retry_after'=>$this->window];
    }
}
