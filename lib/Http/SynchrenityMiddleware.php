<?php
namespace Synchrenity\Http;

/**
 * SynchrenityMiddleware: Stackable, before/after hooks, error handling, security
 */
class SynchrenityMiddleware
{
    public function handle($request, $next = null)
    {
        // Override in child classes for before/after logic
        if ($next) {
            return $next($request);
        }
        return $request;
    }
}
