<?php

declare(strict_types=1);

namespace Synchrenity\Http;

/**
 * Synchrenity Http Response: Flexible, secure, feature-rich
 */
class SynchrenityResponse
{
    protected $status  = 200;
    protected $headers = [];
    protected $body    = '';
    protected $sent    = false;
    protected $plugins = [];
    protected $events  = [];
    protected $metrics = [
        'sent'    => 0,
        'json'    => 0,
        'file'    => 0,
        'stream'  => 0,
        'cookies' => 0,
    ];

    public function __construct($body = '', $status = 200, array $headers = [])
    {
        $this->body    = $body;
        $this->status  = $status;
        $this->headers = $headers;
    }

    public function setHeader($name, $value)
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function getHeader($name)
    {
        return $this->headers[$name] ?? null;
    }

    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setBody($body)
    {
        $this->body = $body;

        return $this;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function send()
    {
        if ($this->sent) {
            return;
        }
        $this->sent = true;
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        // Plugins before send
        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'beforeSend'])) {
                $plugin->beforeSend($this);
            }
        }
        $this->triggerEvent('beforeSend');
        echo $this->body;
        $this->metrics['sent']++;

        // Plugins after send
        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'afterSend'])) {
                $plugin->afterSend($this);
            }
        }
        $this->triggerEvent('afterSend');
    }

    public function json($data, $status = 200, $headers = [], $pretty = false)
    {
        $this->setHeader('Content-Type', 'application/json');
        $this->setStatus($status);

        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
        $this->setBody($pretty ? json_encode($data, JSON_PRETTY_PRINT) : json_encode($data));
        $this->metrics['json']++;
        $this->triggerEvent('json');
        $this->send();
    }

    public function xml($data, $status = 200, $headers = [])
    {
        $this->setHeader('Content-Type', 'application/xml');
        $this->setStatus($status);

        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
        $xml = $this->arrayToXml($data);
        $this->setBody($xml);
        $this->triggerEvent('xml');
        $this->send();
    }

    protected function arrayToXml($data, $root = 'response')
    {
        $xml = new \SimpleXMLElement("<{$root}/>");
        $f   = function ($f, $data, $xml) {
            foreach ($data as $k => $v) {
                if (is_array($v)) {
                    $child = $xml->addChild(is_numeric($k) ? 'item' : $k);
                    $f($f, $v, $child);
                } else {
                    $xml->addChild(is_numeric($k) ? 'item' : $k, htmlspecialchars($v));
                }
            }
        };
        $f($f, $data, $xml);

        return $xml->asXML();
    }

    public function file($filePath, $downloadName = null, $inline = false)
    {
        if (!file_exists($filePath)) {
            $this->setStatus(404)->setBody('File not found')->send();

            return;
        }
        $this->setHeader('Content-Type', mime_content_type($filePath));
        $disp = $inline ? 'inline' : 'attachment';
        $this->setHeader('Content-Disposition', $disp . '; filename="' . basename($downloadName ?: $filePath) . '"');
        $this->setBody(file_get_contents($filePath));
        $this->metrics['file']++;
        $this->triggerEvent('file');
        $this->send();
    }

    public function stream($resource, $chunkSize = 8192, $close = true)
    {
        $this->metrics['stream']++;
        $this->triggerEvent('stream');

        while (!feof($resource)) {
            echo fread($resource, $chunkSize);
        }

        if ($close) {
            fclose($resource);
        }
    }

    public function setCookie($name, $value, $expire = 0, $secure = true, $httpOnly = true, $sameSite = 'Lax')
    {
        $params = [
            'expires'  => $expire,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ];
        setcookie($name, $value, $params);
        $this->metrics['cookies']++;
        $this->triggerEvent('cookie', $name);
    }

    public function securityHeaders()
    {
        $this->setHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->setHeader('X-Content-Type-Options', 'nosniff');
        $this->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->setHeader('Content-Security-Policy', "default-src 'self';");
        $this->setHeader('X-XSS-Protection', '1; mode=block');
        $this->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $this->setHeader('Permissions-Policy', 'geolocation=(), microphone=()');
        $this->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    // CORS
    public function cors($origin = '*', $methods = 'GET,POST,PUT,DELETE,OPTIONS', $headers = 'Content-Type,Authorization')
    {
        $this->setHeader('Access-Control-Allow-Origin', $origin);
        $this->setHeader('Access-Control-Allow-Methods', $methods);
        $this->setHeader('Access-Control-Allow-Headers', $headers);
        $this->setHeader('Access-Control-Allow-Credentials', 'true');
    }

    // Plugin system
    public function registerPlugin($plugin)
    {
        $this->plugins[] = $plugin;
    }

    // Event system
    public function on($event, callable $cb)
    {
        $this->events[$event][] = $cb;
    }
    protected function triggerEvent($event, $data = null)
    {
        foreach ($this->events[$event] ?? [] as $cb) {
            call_user_func($cb, $data, $this);
        }
    }

    // Metrics
    public function getMetrics()
    {
        return $this->metrics;
    }

    // Introspection
    public function getHeaders()
    {
        return $this->headers;
    }
    public function isSent()
    {
        return $this->sent;
    }
}
