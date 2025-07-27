<?php
namespace Synchrenity\Http;

/**
 * Synchrenity Http Request: Immutable, secure, feature-rich
 */
class SynchrenityRequest
{
    protected $method;
    protected $uri;
    protected $headers = [];
    protected $cookies = [];
    protected $query = [];
    protected $body = [];
    protected $files = [];
    protected $ip;
    protected $secure;
    protected $rawBody;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->headers = self::parseHeaders();
        $this->cookies = $_COOKIE;
        $this->query = $_GET;
        $this->body = $_POST;
        $this->files = $_FILES;
        $this->ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $this->rawBody = file_get_contents('php://input');
    }

    public static function parseHeaders()
    {
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (strpos($k, 'HTTP_') === 0) {
                $name = str_replace('_', '-', substr($k, 5));
                $headers[$name] = $v;
            }
        }
        return $headers;
    }

    public function method() { return $this->method; }
    public function uri() { return $this->uri; }
    public function headers() { return $this->headers; }
    public function cookies() { return $this->cookies; }
    public function query() { return $this->query; }
    public function body() { return $this->body; }
    public function files() { return $this->files; }
    public function ip() { return $this->ip; }
    public function isSecure() { return $this->secure; }
    public function rawBody() { return $this->rawBody; }

    // Advanced: JSON, XML, input validation, etc. can be added here
}
