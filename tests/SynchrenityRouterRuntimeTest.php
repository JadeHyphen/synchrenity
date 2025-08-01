<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Synchrenity\Http\SynchrenityRouter;

require_once __DIR__ . '/../lib/Http/SynchrenityRouter.php';

class DummyRequest
{
    public function uri()
    {
        return '/test';
    }
    public function method()
    {
        return 'GET';
    }
    public function response()
    {
        return new class () {
            public $headers = [];
            public function setHeader($k, $v)
            {
                $this->headers[$k] = $v;
            }
        };
    }
}

class SynchrenityRouterRuntimeTest extends TestCase
{
    public function testRouteMatchAndDispatch()
    {
        $router = new SynchrenityRouter();
        $router->add('GET', '/test', function ($req, $params) {
            return 'OK';
        }, [], 'test_route', ['id' => '\d+']);

        $request              = new DummyRequest();
        list($route, $params) = $router->match($request);
        $this->assertNotNull($route, 'Route should be matched');
        $this->assertEquals('/test', $route['path'], 'Route path should be /test');

        $response = $router->dispatch($request);

        // Handle both string and SynchrenityResponse object
        if (is_object($response) && method_exists($response, '__toString')) {
            $responseStr = (string)$response;
            $this->assertEquals('OK', $responseStr, 'Dispatch should return OK as string');
        } elseif (is_object($response) && property_exists($response, 'body')) {
            $this->assertEquals('OK', $response->body, 'Dispatch should return OK in response body');
        } else {
            $this->assertEquals('OK', $response, 'Dispatch should return OK');
        }
    }
}
