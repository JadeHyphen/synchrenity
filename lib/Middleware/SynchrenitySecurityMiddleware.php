<?php
namespace Synchrenity\Middleware;

/**
 * Advanced security middleware: CSRF, XSS, CORS, headers, and pluggable policies.
 */
class SynchrenitySecurityMiddleware implements SynchrenityMiddlewareInterface {
    protected $context = [];
    protected $policies = [];
    protected $hooks = [];

    public function setContext(array $context) { $this->context = $context; }
    public function before($request) { $this->triggerHook('before', $request); }
    public function after($request, $response) { $this->triggerHook('after', $request, $response); }
    public function onError($request, $exception) { $this->triggerHook('error', $request, $exception); return null; }

    public function addPolicy(callable $policy) { $this->policies[] = $policy; }
    public function addHook($event, callable $cb) { $this->hooks[$event][] = $cb; }
    protected function triggerHook($event, ...$args) { foreach ($this->hooks[$event] ?? [] as $cb) call_user_func_array($cb, $args); }

    public function handle($request, callable $next) {
        $this->before($request);
        // CSRF protection (token in session and request)
        if (isset($this->context['csrf_token'])) {
            $token = $request['headers']['X-CSRF-Token'] ?? $request['csrf_token'] ?? null;
            if ($token !== $this->context['csrf_token']) {
                $this->triggerHook('csrf_failed', $request);
                return ['error'=>'CSRF validation failed','status'=>419];
            }
        }
        // XSS protection (sanitize input)
        if (isset($request['body'])) {
            $request['body'] = $this->sanitize($request['body']);
        }
        // CORS headers
        if (isset($request['headers'])) {
            $request['headers']['Access-Control-Allow-Origin'] = $this->context['cors_origin'] ?? '*';
            $request['headers']['Access-Control-Allow-Methods'] = 'GET,POST,PUT,DELETE,OPTIONS';
            $request['headers']['Access-Control-Allow-Headers'] = 'Content-Type,Authorization';
        }
        // Security headers
        if (isset($request['headers'])) {
            $request['headers']['X-Frame-Options'] = 'DENY';
            $request['headers']['X-Content-Type-Options'] = 'nosniff';
            $request['headers']['Referrer-Policy'] = 'no-referrer';
        }
        // Pluggable policies
        foreach ($this->policies as $policy) {
            $result = $policy($request, $this->context);
            if ($result === false) {
                $this->triggerHook('policy_failed', $request);
                return ['error'=>'Security policy failed','status'=>403];
            }
        }
        $response = $next($request);
        $this->after($request, $response);
        return $response;
    }

    protected function sanitize($data) {
        if (is_array($data)) {
            foreach ($data as $k => $v) $data[$k] = $this->sanitize($v);
            return $data;
        }
        return is_string($data) ? htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $data;
    }
}
