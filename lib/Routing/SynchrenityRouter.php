<?php
namespace Synchrenity\Routing;



class SynchrenityRouter {
    protected $routes = [];
    protected $namedRoutes = [];
    protected $middleware = [];
    protected $currentGroup = null;
    protected $routeCache = [];
    protected $notFoundHandler = null;
    protected $methodNotAllowedHandler = null;
    protected $routePriorities = [];
    protected $paramDefaults = [];
    protected $paramCasts = [];
    protected $domains = [];
    protected $routeVersions = [];
    protected $eventHooks = [
        'beforeDispatch' => [],
        'afterDispatch' => [],
    ];
    protected $routeTags = [];
    protected $routeMetadata = [];
    protected $deprecatedRoutes = [];
    protected $routeStats = [];
    protected $routeAliases = [];
    protected $accessControl = null;
    // Route tags/metadata
    public function tagRoute($name, $tag) { $this->routeTags[$name][] = $tag; }
    public function setRouteMetadata($name, $meta) { $this->routeMetadata[$name] = $meta; }
    public function getRouteMetadata($name) { return $this->routeMetadata[$name] ?? []; }
    // Route deprecation
    public function deprecateRoute($name, $message = 'Deprecated') { $this->deprecatedRoutes[$name] = $message; }
    public function isRouteDeprecated($name) { return isset($this->deprecatedRoutes[$name]); }
    public function getDeprecationMessage($name) { return $this->deprecatedRoutes[$name] ?? null; }
    // Route health check
    public function healthCheck($name) { return isset($this->namedRoutes[$name]); }
    // Route access control
    public function setAccessControl($cb) { $this->accessControl = $cb; }
    // Route statistics
    public function incrementRouteStat($name, $key = 'hits') { $this->routeStats[$name][$key] = ($this->routeStats[$name][$key] ?? 0) + 1; }
    public function getRouteStats($name) { return $this->routeStats[$name] ?? []; }
    // Route aliasing
    public function alias($alias, $name) { if (isset($this->namedRoutes[$name])) $this->namedRoutes[$alias] = $this->namedRoutes[$name]; $this->routeAliases[$alias] = $name; }
    public function resolveAlias($alias) { return $this->routeAliases[$alias] ?? $alias; }
    // Route import/export
    public function exportRoutes($file) { file_put_contents($file, json_encode($this->routes, JSON_PRETTY_PRINT)); }
    public function importRoutes($file) { if (file_exists($file)) $this->routes = json_decode(file_get_contents($file), true); }
    // Route test utilities
    public function testRoute($method, $uri, $expected) { $result = $this->dispatch($method, $uri); return $result === $expected; }
    // Route cache invalidation
    public function invalidateRouteCache() { $this->routeCache = []; }
    // Route parameter defaults
    public function setParamDefault($param, $value) { $this->paramDefaults[$param] = $value; }
    // Route parameter type casting
    public function setParamCast($param, $type) { $this->paramCasts[$param] = $type; }
    // Route versioning
    public function setRouteVersion($name, $version, $route) { $this->routeVersions[$name][$version] = $route; }
    public function useRouteVersion($name, $version) { if (isset($this->routeVersions[$name][$version])) $this->namedRoutes[$name] = $this->routeVersions[$name][$version]; }
    // Route event hooks
    public function on($event, $cb) { if (isset($this->eventHooks[$event])) $this->eventHooks[$event][] = $cb; }
    protected function trigger($event, ...$args) { if (isset($this->eventHooks[$event])) foreach ($this->eventHooks[$event] as $cb) call_user_func_array($cb, $args); }
    // Route grouping by domain
    public function domain($domain, callable $callback) {
        $parentGroup = $this->currentGroup;
        $this->currentGroup = array_merge($this->currentGroup ?? [], ['domain' => $domain]);
        $callback($this);
        $this->currentGroup = $parentGroup;
    }
    // Route introspection
    public function allRoutes() { return $this->routes; }
    public function allNamedRoutes() { return $this->namedRoutes; }
    // Route reverse lookup
    public function findRouteByHandler($handler) {
        foreach ($this->routes as $route) {
            if ($route['handler'] === $handler) return $route;
        }
        return null;
    }
    // Route debug output
    public function debug() {
        return print_r($this->routes, true);
    }

    public function group(array $attributes, callable $callback) {
        $parentGroup = $this->currentGroup;
        $this->currentGroup = $parentGroup ? array_merge_recursive($parentGroup, $attributes) : $attributes;
        $callback($this);
        $this->currentGroup = $parentGroup;
    }

    public function add($method, $path, $handler, $name = null, $middleware = [], $priority = 0, $constraints = []) {
        $route = [
            'method' => strtoupper($method),
            'path' => $this->applyGroupPrefix($path),
            'handler' => $handler,
            'middleware' => array_merge($this->currentGroup['middleware'] ?? [], $middleware),
            'constraints' => $constraints,
            'priority' => $priority,
        ];
        $this->routes[] = $route;
        if ($name) {
            $this->namedRoutes[$name] = $route;
        }
        if ($priority) {
            $this->routePriorities[] = $priority;
        }
    }

    public function get($path, $handler, $name = null, $middleware = [], $priority = 0, $constraints = []) {
        $this->add('GET', $path, $handler, $name, $middleware, $priority, $constraints);
    }
    public function post($path, $handler, $name = null, $middleware = [], $priority = 0, $constraints = []) {
        $this->add('POST', $path, $handler, $name, $middleware, $priority, $constraints);
    }
    public function put($path, $handler, $name = null, $middleware = [], $priority = 0, $constraints = []) {
        $this->add('PUT', $path, $handler, $name, $middleware, $priority, $constraints);
    }
    public function delete($path, $handler, $name = null, $middleware = [], $priority = 0, $constraints = []) {
        $this->add('DELETE', $path, $handler, $name, $middleware, $priority, $constraints);
    }
    // RESTful resource routes
    public function resource($name, $controller, $middleware = []) {
        $this->get("/$name", [$controller, 'index'], $name.'.index', $middleware);
        $this->get("/$name/create", [$controller, 'create'], $name.'.create', $middleware);
        $this->post("/$name", [$controller, 'store'], $name.'.store', $middleware);
        $this->get("/$name/{id}", [$controller, 'show'], $name.'.show', $middleware);
        $this->get("/$name/{id}/edit", [$controller, 'edit'], $name.'.edit', $middleware);
        $this->put("/$name/{id}", [$controller, 'update'], $name.'.update', $middleware);
        $this->delete("/$name/{id}", [$controller, 'destroy'], $name.'.destroy', $middleware);
    }

    public function dispatch($method, $uri) {
        // Access control
        if ($this->accessControl && !call_user_func($this->accessControl, $method, $uri)) {
            http_response_code(403);
            return '403 Forbidden';
        }
        $allowed = [];
        $matched = false;
        $bestRoute = null;
        $params = [];
        $this->trigger('beforeDispatch', $method, $uri);
        // Sort by priority (desc)
        usort($this->routes, function($a, $b) { return ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0); });
        foreach ($this->routes as $route) {
            // Domain check
            if (isset($route['domain']) && isset($_SERVER['HTTP_HOST']) && $route['domain'] !== $_SERVER['HTTP_HOST']) continue;
            if ($this->match($route['path'], $uri, $params, $route['constraints'])) {
                // Param defaults
                foreach ($this->paramDefaults as $k => $v) {
                    if (!isset($params[$k])) $params[$k] = $v;
                }
                // Param type casting
                foreach ($this->paramCasts as $k => $type) {
                    if (isset($params[$k])) settype($params[$k], $type);
                }
                if ($route['method'] === strtoupper($method)) {
                    // Deprecation warning
                    $routeName = array_search($route, $this->namedRoutes, true);
                    if ($routeName && $this->isRouteDeprecated($routeName)) {
                        header('X-Deprecation-Notice: ' . $this->getDeprecationMessage($routeName));
                    }
                    $this->incrementRouteStat($routeName ?: $route['path']);
                    $matched = true;
                    $handler = $route['handler'];
                    $middlewareStack = $route['middleware'];
                    $request = ['method' => $method, 'uri' => $uri, 'params' => $params];
                    $result = $this->runMiddleware($middlewareStack, $request, $handler);
                    $this->trigger('afterDispatch', $method, $uri, $result);
                    return $result;
                } else {
                    $allowed[] = $route['method'];
                }
            }
        }
        if ($allowed) {
            http_response_code(405);
            if ($this->methodNotAllowedHandler) return call_user_func($this->methodNotAllowedHandler, $method, $uri, $allowed);
            return '405 Method Not Allowed';
        }
        http_response_code(404);
        if ($this->notFoundHandler) return call_user_func($this->notFoundHandler, $method, $uri);
        return '404 Not Found';
    }

    protected function match($routePath, $uri, &$params, $constraints = []) {
        // Support optional params: /foo/{bar?}
        $pattern = preg_replace_callback('#\{([a-zA-Z0-9_]+)(\?)?(?::([^}]+))?\}#', function($m) use ($constraints) {
            $name = $m[1];
            $optional = !empty($m[2]);
            $type = $m[3] ?? ($constraints[$name] ?? '[^/]+');
            $regex = '(?P<' . $name . '>' . $type . ')';
            return $optional ? $regex . '?' : $regex;
        }, $routePath);
        $pattern = '#^' . $pattern . '$#';
        if (preg_match($pattern, $uri, $matches)) {
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            return true;
        }
        $params = [];
        return false;
    }

    protected function runMiddleware($middlewareStack, $request, $handler) {
        $next = function($req) use (&$handler) {
            if (is_callable($handler)) {
                return call_user_func($handler, $req);
            }
            return null;
        };
        while ($mw = array_pop($middlewareStack)) {
            $next = function($req) use ($mw, $next) {
                return call_user_func($mw, $req, $next);
            };
        }
        return $next($request);
    }

    protected function applyGroupPrefix($path) {
        if ($this->currentGroup && isset($this->currentGroup['prefix'])) {
            return rtrim($this->currentGroup['prefix'], '/') . '/' . ltrim($path, '/');
        }
        return $path;
    }

    public function route($name, $params = []) {
        if (!isset($this->namedRoutes[$name])) {
            throw new \Exception("Route '$name' not found.");
        }
        $path = $this->namedRoutes[$name]['path'];
        foreach ($params as $k => $v) {
            $path = preg_replace('/\{' . preg_quote($k, '/') . '(\?|:[^}]+)?\}/', $v, $path);
        }
        // Remove optional params not provided
        $path = preg_replace('/\{[a-zA-Z0-9_]+\?\}/', '', $path);
        return $path;
    }

    // Route caching
    public function cacheRoutes($file) {
        file_put_contents($file, serialize($this->routes));
    }
    public function loadRouteCache($file) {
        if (file_exists($file)) {
            $this->routes = unserialize(file_get_contents($file));
        }
    }

    // Custom 404/405 handlers
    public function setNotFoundHandler($cb) { $this->notFoundHandler = $cb; }
    public function setMethodNotAllowedHandler($cb) { $this->methodNotAllowedHandler = $cb; }

    // API output
    public function toJson() {
        return json_encode(['routes'=>$this->routes], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    }
}
