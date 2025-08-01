<?php

declare(strict_types=1);

namespace Synchrenity\Middleware;

/**
 * Robust, extensible middleware manager supporting advanced features.
 */
class SynchrenityMiddlewareManager
{
    protected $middleware    = [];
    protected $context       = [];
    protected $errorHandlers = [];
    protected $hooks         = [];

    public function add($middleware)
    {
        if (!is_object($middleware) || !method_exists($middleware, 'handle')) {
            throw new \InvalidArgumentException('Middleware must be an object with a handle method');
        }
        $this->middleware[] = $middleware;

        return $this;
    }

    public function setContext(array $context)
    {
        $this->context = $context;

        foreach ($this->middleware as $mw) {
            if (method_exists($mw, 'setContext')) {
                $mw->setContext($context);
            }
        }

        return $this;
    }

    public function addErrorHandler(callable $handler)
    {
        if (!is_callable($handler)) {
            throw new \InvalidArgumentException('Error handler must be callable');
        }
        $this->errorHandlers[] = $handler;

        return $this;
    }

    public function addHook($event, callable $cb)
    {
        if (!is_string($event) || !is_callable($cb)) {
            throw new \InvalidArgumentException('Hook event must be string and callback must be callable');
        }
        $this->hooks[$event][] = $cb;

        return $this;
    }

    protected function triggerHook($event, ...$args)
    {
        foreach ($this->hooks[$event] ?? [] as $cb) {
            call_user_func_array($cb, $args);
        }
    }

    public function handle($request)
    {
        $this->triggerHook('before', $request);
        $middleware    = $this->middleware;
        $context       = $this->context;
        $errorHandlers = $this->errorHandlers;
        $index         = 0;
        $next          = function ($req) use (&$middleware, &$index, &$next, $context, $errorHandlers) {
            if (!isset($middleware[$index])) {
                return $req;
            }
            $mw = $middleware[$index++];

            try {
                if (method_exists($mw, 'before')) {
                    $mw->before($req);
                }
                $resp = $mw->handle($req, $next);

                if (method_exists($mw, 'after')) {
                    $mw->after($req, $resp);
                }

                return $resp;
            } catch (\Throwable $e) {
                if (method_exists($mw, 'onError')) {
                    return $mw->onError($req, $e);
                }

                foreach ($errorHandlers as $handler) {
                    return $handler($req, $e);
                }

                throw $e;
            }
        };
        $response = $next($request);
        $this->triggerHook('after', $request, $response);

        return $response;
    }
}
