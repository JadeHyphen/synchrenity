<?php

declare(strict_types=1);

namespace Synchrenity\Middleware;

/**
 * Interface for robust, extensible middleware components.
 */
interface SynchrenityMiddlewareInterface
{
    /**
     * Process an incoming request and return a response, optionally delegating to the next middleware.
     */
    public function handle($request, callable $next);

    /**
     * Optionally, provide hooks for before/after, error handling, and context injection.
     */
    public function before($request);
    public function after($request, $response);
    public function onError($request, $exception);
    public function setContext(array $context);
}
