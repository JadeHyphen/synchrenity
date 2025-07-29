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
    protected $json = null;
    protected $xml = null;
    protected $plugins = [];
    protected $events = [];
    protected $metrics = [
        'gets' => 0,
        'json' => 0,
        'xml' => 0,
        'validations' => 0
    ];

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
        $this->parseJson();
        $this->parseXml();
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
        // PHP CGI/FPM Authorization
        if (isset($_SERVER['CONTENT_TYPE'])) $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        if (isset($_SERVER['CONTENT_LENGTH'])) $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
        if (isset($_SERVER['Authorization'])) $headers['Authorization'] = $_SERVER['Authorization'];
        return $headers;
    }

    // Parse JSON body
    protected function parseJson() {
        if (isset($this->headers['Content-Type']) && strpos($this->headers['Content-Type'], 'application/json') !== false) {
            $this->json = json_decode($this->rawBody, true);
        }
    }
    public function json() {
        $this->metrics['json']++;
        $this->triggerEvent('json');
        return $this->json;
    }

    // Parse XML body
    protected function parseXml() {
        if (isset($this->headers['Content-Type']) && strpos($this->headers['Content-Type'], 'xml') !== false) {
            $this->xml = simplexml_load_string($this->rawBody);
        }
    }
    public function xml() {
        $this->metrics['xml']++;
        $this->triggerEvent('xml');
        return $this->xml;
    }

    public function method() { $this->metrics['gets']++; $this->triggerEvent('method'); return $this->method; }
    public function uri() { $this->metrics['gets']++; $this->triggerEvent('uri'); return $this->uri; }
    public function headers() { $this->metrics['gets']++; $this->triggerEvent('headers'); return $this->headers; }
    public function cookies() { $this->metrics['gets']++; $this->triggerEvent('cookies'); return $this->cookies; }
    public function query() { $this->metrics['gets']++; $this->triggerEvent('query'); return $this->query; }
    public function body() { $this->metrics['gets']++; $this->triggerEvent('body'); return $this->body; }
    public function files() { $this->metrics['gets']++; $this->triggerEvent('files'); return $this->files; }
    public function ip() { $this->metrics['gets']++; $this->triggerEvent('ip'); return $this->ip; }
    public function isSecure() { $this->metrics['gets']++; $this->triggerEvent('isSecure'); return $this->secure; }
    public function rawBody() { $this->metrics['gets']++; $this->triggerEvent('rawBody'); return $this->rawBody; }

    // Content negotiation
    public function accepts($type) {
        $accept = $this->headers['Accept'] ?? '';
        return stripos($accept, $type) !== false;
    }

    // Input validation
    public function validate($rules) {
        $this->metrics['validations']++;
        $this->triggerEvent('validate', $rules);
        $data = $this->json ?? $this->body;
        $errors = [];
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            if (is_callable($rule)) {
                if (!$rule($value)) $errors[$field] = 'Invalid';
            } elseif ($rule === 'required' && ($value === null || $value === '')) {
                $errors[$field] = 'Required';
            }
        }
        return $errors;
    }

    // Security helpers
    public function bearerToken() {
        $auth = $this->headers['Authorization'] ?? '';
        if (stripos($auth, 'Bearer ') === 0) return substr($auth, 7);
        return null;
    }
    public function userAgent() {
        return $this->headers['User-Agent'] ?? '';
    }

    // Plugin system
    public function registerPlugin($plugin) { $this->plugins[] = $plugin; }

    // Event system
    public function on($event, callable $cb) { $this->events[$event][] = $cb; }
    protected function triggerEvent($event, $data = null) {
        foreach ($this->events[$event] ?? [] as $cb) call_user_func($cb, $data, $this);
    }

    // Metrics
    public function getMetrics() { return $this->metrics; }

    // Introspection
    public function getAll() {
        return [
            'method' => $this->method,
            'uri' => $this->uri,
            'headers' => $this->headers,
            'cookies' => $this->cookies,
            'query' => $this->query,
            'body' => $this->body,
            'files' => $this->files,
            'ip' => $this->ip,
            'secure' => $this->secure,
            'rawBody' => $this->rawBody,
            'json' => $this->json,
            'xml' => $this->xml
        ];
    }
}
