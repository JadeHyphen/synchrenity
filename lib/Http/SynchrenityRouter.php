<?php

declare(strict_types=1);

namespace Synchrenity\Http;

/**
 * SynchrenityRouter: Fast, RESTful, supports middleware, route groups, named routes, parameter validation
 */

class SynchrenityRouter
{
    protected array $routes           = [];
    protected array $namedRoutes      = [];
    protected array $globalMiddleware = [];
    protected array $constraints      = [];
    protected $fallbackHandler  = null;
    protected array $redirects        = [];
    protected array $subdomainRoutes  = [];
    protected array $routeCache       = [];
    protected $eventManager;
    protected array $rateLimiters = [];
    protected array $priorities   = [];
    protected array $versions     = [];
    // Advanced
    protected array $routeTags    = [];
    protected array $routeHealth  = [];
    protected array $routeMetrics = [];
    protected array $plugins      = [];
    protected array $routeEvents  = [];

    public function __construct($eventManager = null)
    {
        $this->eventManager = $eventManager;
    }

    public function add(string $method, string $path, $handler, array $middleware = [], ?string $name = null, array $constraints = [], array $tags = [], string $health = 'healthy', bool $deprecated = false): void
    {
        if (empty($method) || empty($path)) {
            throw new \InvalidArgumentException('Method and path cannot be empty');
        }

        if (!is_callable($handler) && !is_array($handler) && !is_string($handler)) {
            throw new \InvalidArgumentException('Handler must be callable, array, or string');
        }

        $route = [
            'method'      => strtoupper($method),
            'path'        => $path,
            'handler'     => $handler,
            'middleware'  => $middleware,
            'constraints' => $constraints,
            'priority'    => $this->priorities[$path] ?? 0,
            'version'     => $this->versions[$path]   ?? null,
            'tags'        => $tags,
            'health'      => $health,
            'deprecated'  => $deprecated,
        ];
        $this->routes[] = $route;

        if ($name) {
            $this->namedRoutes[$name] = $route;
        }

        // Plugin hooks
        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'onRouteAdd'])) {
                $plugin->onRouteAdd($route, $this);
            }
        }
        $this->triggerRouteEvent('add', $route);
    }

    // Plugin system
    public function registerPlugin($plugin)
    {
        $this->plugins[] = $plugin;
    }

    // Route event system
    public function onRouteEvent($event, callable $cb)
    {
        $this->routeEvents[$event][] = $cb;
    }
    protected function triggerRouteEvent($event, $route)
    {
        foreach ($this->routeEvents[$event] ?? [] as $cb) {
            call_user_func($cb, $route, $this);
        }
    }

    // Tag routes
    public function tagRoute($path, $tag)
    {
        $this->routeTags[$path][] = $tag;
    }
    public function getRouteTags($path)
    {
        return $this->routeTags[$path] ?? [];
    }

    // Route health
    public function setRouteHealth($path, $status)
    {
        $this->routeHealth[$path] = $status;
    }
    public function getRouteHealth($path)
    {
        return $this->routeHealth[$path] ?? 'unknown';
    }

    // Route metrics
    public function incrementRouteMetric($path, $metric)
    {
        if (!isset($this->routeMetrics[$path])) {
            $this->routeMetrics[$path] = [];
        }

        if (!isset($this->routeMetrics[$path][$metric])) {
            $this->routeMetrics[$path][$metric] = 0;
        }
        $this->routeMetrics[$path][$metric]++;
    }
    public function getRouteMetrics($path)
    {
        return $this->routeMetrics[$path] ?? [];
    }

    // Route search/filter
    public function findRoutes(callable $filter)
    {
        return array_filter($this->routes, $filter);
    }

    // Deprecate route
    public function deprecateRoute($path)
    {
        foreach ($this->routes as &$route) {
            if ($route['path'] === $path) {
                $route['deprecated'] = true;
            }
        }
    }

    public function resource($name, $controller)
    {
        $this->add('GET', "/$name", [$controller, 'index']);
        $this->add('GET', "/$name/create", [$controller, 'create']);
        $this->add('POST', "/$name", [$controller, 'store']);
        $this->add('GET', "/$name/{id}", [$controller, 'show']);
        $this->add('GET', "/$name/{id}/edit", [$controller, 'edit']);
        $this->add('PUT', "/$name/{id}", [$controller, 'update']);
        $this->add('DELETE', "/$name/{id}", [$controller, 'destroy']);

        // Add versioned resource routes
        if (!empty($this->versions)) {
            foreach ($this->versions as $path => $version) {
                $this->add('GET', "/v$version/$name", [$controller, 'index']);
            }
        }
    }

    public function constraint($param, $regex)
    {
        $this->constraints[$param] = $regex;
    }

    public function priority($path, $priority)
    {
        $this->priorities[$path] = $priority;
    }

    public function version($path, $version)
    {
        $this->versions[$path] = $version;
    }

    public function rateLimit($path, $limiter)
    {
        $this->rateLimiters[$path] = $limiter;
    }

    public function middleware($mw)
    {
        $this->globalMiddleware[] = $mw;
    }

    public function group($prefix, $callback, $middleware = [])
    {
        $callback(new class ($this, $prefix, $middleware) {
            private $router;
            private $prefix;
            private $middleware;
            public function __construct($router, $prefix, $middleware)
            {
                $this->router     = $router;
                $this->prefix     = $prefix;
                $this->middleware = $middleware;
            }
            public function add($method, $path, $handler, $mw = [], $name = null, $constraints = [])
            {
                $this->router->add($method, $this->prefix . $path, $handler, array_merge($this->middleware, $mw), $name, $constraints);
            }
        });
    }

    public function subdomain($sub, $method, $path, $handler, $middleware = [], $name = null)
    {
        $this->subdomainRoutes[$sub][] = [
            'method'     => strtoupper($method),
            'path'       => $path,
            'handler'    => $handler,
            'middleware' => $middleware,
            'name'       => $name,
        ];
    }

    public function fallback($handler)
    {
        $this->fallbackHandler = $handler;
    }

    public function redirect($from, $to, $status = 302)
    {
        $this->redirects[$from] = ['to' => $to, 'status' => $status];
    }

    public function match($request)
    {
        $uri    = $request->uri();
        $method = $request->method();

        // Check redirects
        if (isset($this->redirects[$uri])) {
            $redir = $this->redirects[$uri];

            return [['redirect' => true, 'to' => $redir['to'], 'status' => $redir['status']], []];
        }
        // Subdomain routing
        $host = $_SERVER['HTTP_HOST']  ?? '';
        $sub  = explode('.', $host)[0] ?? '';

        if (isset($this->subdomainRoutes[$sub])) {
            foreach ($this->subdomainRoutes[$sub] as $route) {
                if ($route['method'] === $method && $route['path'] === $uri) {
                    return [$route, []];
                }
            }
        }
        // Route matching with constraints
        $sortedRoutes = $this->routes;
        usort($sortedRoutes, function ($a, $b) {
            return ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0);
        });

        foreach ($sortedRoutes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            // Version check
            if (isset($route['version']) && $route['version'] !== null) {
                if (!preg_match('#^/v' . $route['version'] . '#', $uri)) {
                    continue;
                }
            }
            $pattern = preg_replace_callback('#\{([a-zA-Z0-9_]+)\}#', function ($m) use ($route) {
                $param = $m[1];
                $regex = $route['constraints'][$param] ?? '[^/]+';

                return '(?P<' . $param . '>' . $regex . ')';
            }, $route['path']);

            if (preg_match('#^' . $pattern . '$#', $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Rate limiting
                if (isset($this->rateLimiters[$route['path']])) {
                    $limiter = $this->rateLimiters[$route['path']];

                    if (!$limiter->allow($request)) {
                        return [null, []];
                    }
                }

                return [$route, $params];
            }
        }

        return [null, []];
    }

    public function dispatch($request)
    {
        if ($this->eventManager) {
            $this->eventManager->dispatch('router.dispatch.before', $request);
        }

        foreach ($this->globalMiddleware as $mw) {
            $mw->handle($request);
        }
        list($route, $params) = $this->match($request);

        if ($route) {
            if (!empty($route['redirect'])) {
                $resp = new \Synchrenity\Http\SynchrenityResponse('', $route['status'] ?? 302);
                $resp->setHeader('Location', $route['to']);
                $resp->send();

                return $resp;
            }

            foreach ($route['middleware'] as $mw) {
                $mw->handle($request);
            }

            // Secure input validation stub
            foreach ($params as $k => $v) {
                if (isset($route['constraints'][$k]) && !preg_match('#' . $route['constraints'][$k] . '#', $v)) {
                    return new \Synchrenity\Http\SynchrenityResponse('Invalid parameter', 400);
                }
            }

            // SEO helpers: set canonical URL header
            if (isset($route['name'])) {
                $canonical = $this->url($route['name'], $params);

                if ($canonical) {
                    if (method_exists($request, 'response')) {
                        $request->response()->setHeader('Link', '<' . $canonical . '>; rel="canonical"');
                    }
                }
            }
            $resp = call_user_func($route['handler'], $request, $params);

            if ($this->eventManager) {
                $this->eventManager->dispatch('router.dispatch.after', $request, $resp);
            }

            return $resp;
        }

        if ($this->fallbackHandler) {
            return call_user_func($this->fallbackHandler, $request);
        }

        return new \Synchrenity\Http\SynchrenityResponse('Not Found', 404);
    }

    public function url($name, $params = [])
    {
        if (!isset($this->namedRoutes[$name])) {
            return null;
        }
        $path = $this->namedRoutes[$name]['path'];

        foreach ($params as $k => $v) {
            $path = str_replace('{' . $k . '}', $v, $path);
        }

        return $path;
    }

    // Export all routes for documentation or API clients
    public function exportRoutes($filter = null)
    {
        if ($filter && is_callable($filter)) {
            return array_values(array_filter($this->routes, $filter));
        }

        return $this->routes;
    }

    // Import routes from array
    public function importRoutes($routes)
    {
        $this->routes = $routes;
    }

    // Route caching: store current routes in cache
    public function cacheRoutes()
    {
        $this->routeCache = $this->routes;
    }

    // Load cached routes into active routes
    public function loadCachedRoutes()
    {
        if (!empty($this->routeCache)) {
            $this->routes = $this->routeCache;
        }
    }

    // Automated test stub for router (for future extension)
    public function testRoutes()
    {
        // Example: return all route paths for test assertions
        return array_map(function ($r) { return $r['path']; }, $this->routes);
    }

    // Documentation helper: get all route metadata
    public function getRouteDocs($filter = null)
    {
        $routes = $this->routes;

        if ($filter && is_callable($filter)) {
            $routes = array_filter($routes, $filter);
        }

        return array_map(function ($r) {
            return [
                'method'      => $r['method'],
                'path'        => $r['path'],
                'name'        => $r['name'] ?? null,
                'constraints' => $r['constraints'],
                'middleware'  => $r['middleware'],
                'priority'    => $r['priority']   ?? 0,
                'version'     => $r['version']    ?? null,
                'tags'        => $r['tags']       ?? [],
                'health'      => $r['health']     ?? 'unknown',
                'deprecated'  => $r['deprecated'] ?? false,
            ];
        }, $routes);
    }
}
