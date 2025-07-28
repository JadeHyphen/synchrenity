<?php
namespace Synchrenity\Routing;


class SynchrenityRouter {
    protected $routes = [];
    protected $namedRoutes = [];
    protected $middleware = [];
    protected $currentGroup = null;

    public function group(array $attributes, callable $callback) {
        $parentGroup = $this->currentGroup;
        $this->currentGroup = $attributes;
        $callback($this);
        $this->currentGroup = $parentGroup;
    }

    public function add($method, $path, $handler, $name = null, $middleware = []) {
        $route = [
            'method' => strtoupper($method),
            'path' => $this->applyGroupPrefix($path),
            'handler' => $handler,
            'middleware' => array_merge($this->currentGroup['middleware'] ?? [], $middleware),
        ];
        $this->routes[] = $route;
        if ($name) {
            $this->namedRoutes[$name] = $route;
        }
    }

    public function get($path, $handler, $name = null, $middleware = []) {
        $this->add('GET', $path, $handler, $name, $middleware);
    }
    public function post($path, $handler, $name = null, $middleware = []) {
        $this->add('POST', $path, $handler, $name, $middleware);
    }
    public function put($path, $handler, $name = null, $middleware = []) {
        $this->add('PUT', $path, $handler, $name, $middleware);
    }
    public function delete($path, $handler, $name = null, $middleware = []) {
        $this->add('DELETE', $path, $handler, $name, $middleware);
    }

    public function dispatch($method, $uri) {
        foreach ($this->routes as $route) {
            if ($route['method'] === strtoupper($method) && $this->match($route['path'], $uri, $params)) {
                $handler = $route['handler'];
                $middlewareStack = $route['middleware'];
                $request = ['method' => $method, 'uri' => $uri, 'params' => $params];
                return $this->runMiddleware($middlewareStack, $request, $handler);
            }
        }
        http_response_code(404);
        return '404 Not Found';
    }

    protected function match($routePath, $uri, &$params) {
        $pattern = preg_replace('#\{([a-zA-Z0-9_]+)(:([^}]+))?\}#', '(?P<$1>$3[^/]+)?', $routePath);
        $pattern = '#^' . preg_replace('#\{([a-zA-Z0-9_]+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
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
            $path = preg_replace('/\{' . preg_quote($k, '/') . '(:[^}]+)?\}/', $v, $path);
        }
        return $path;
    }
}
