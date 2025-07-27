<?php
namespace Synchrenity\Http;

class SynchrenityApiRateLimitMiddleware {
    protected $rateLimiter;
    protected $roleResolver;

    public function __construct($rateLimiter, callable $roleResolver = null) {
        $this->rateLimiter = $rateLimiter;
        $this->roleResolver = $roleResolver;
    }

    public function handle($request, $next) {
        $endpoint = $request->getMethod() . ':' . $request->getPath();
        $user = $request->getUserId() ?? 'guest';
        $role = $this->roleResolver ? call_user_func($this->roleResolver, $request) : 'user';
        if (!$this->rateLimiter->check($user, $role, $endpoint)) {
            return new \Synchrenity\Http\SynchrenityResponse('API rate limit exceeded', 429);
        }
        return $next($request);
    }
}
