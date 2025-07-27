<?php
use Synchrenity\Http\SynchrenityRouter;

require_once __DIR__ . '/../lib/Http/SynchrenityRouter.php';

class DummyRequest {
    public function uri() { return '/test'; }
    public function method() { return 'GET'; }
    public function response() { return new class {
        public $headers = [];
        public function setHeader($k, $v) { $this->headers[$k] = $v; }
    }; }
}

$router = new SynchrenityRouter();
$router->add('GET', '/test', function($req, $params) {
    return 'OK';
}, [], 'test_route', ['id' => '\d+']);

$request = new DummyRequest();
list($route, $params) = $router->match($request);
if ($route && $route['path'] === '/test') {
    echo "[PASS] Route matched\n";
} else {
    echo "[FAIL] Route not matched\n";
    exit(1);
}

$response = $router->dispatch($request);
if ($response === 'OK') {
    echo "[PASS] Dispatch returned OK\n";
} else {
    echo "[FAIL] Dispatch did not return OK\n";
    exit(1);
}
