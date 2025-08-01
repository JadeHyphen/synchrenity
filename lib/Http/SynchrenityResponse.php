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
        // Sanitize root element name
        $root = preg_replace('/[^a-zA-Z0-9_-]/', '', $root);
        if (empty($root)) {
            $root = 'response';
        }
        
        $xml = new \SimpleXMLElement("<{$root}/>");
        $f   = function ($f, $data, $xml) {
            foreach ($data as $k => $v) {
                // Sanitize element names
                $elementName = is_numeric($k) ? 'item' : preg_replace('/[^a-zA-Z0-9_-]/', '', $k);
                if (empty($elementName)) {
                    $elementName = 'item';
                }
                
                if (is_array($v)) {
                    $child = $xml->addChild($elementName);
                    $f($f, $v, $child);
                } else {
                    // Properly escape content and handle different data types
                    if (is_bool($v)) {
                        $v = $v ? 'true' : 'false';
                    } elseif (is_null($v)) {
                        $v = '';
                    } else {
                        $v = (string) $v;
                    }
                    
                    // Use CDATA for complex content, htmlspecialchars for simple content
                    if (preg_match('/[&<>"\']+/', $v)) {
                        $child = $xml->addChild($elementName);
                        $dom = dom_import_simplexml($child);
                        $dom->appendChild($dom->ownerDocument->createCDATASection($v));
                    } else {
                        $xml->addChild($elementName, htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
                    }
                }
            }
        };
        $f($f, $data, $xml);

        return $xml->asXML();
    }

    public function file($filePath, $downloadName = null, $inline = false)
    {
        // Security: Validate file path to prevent directory traversal
        $realPath = realpath($filePath);
        if ($realPath === false) {
            $this->setStatus(404)->setBody('File not found')->send();
            return;
        }
        
        // Ensure file is within allowed directory (basic check)
        $allowedPaths = [
            realpath($_SERVER['DOCUMENT_ROOT'] ?? '.'),
            realpath(sys_get_temp_dir()),
            realpath(__DIR__ . '/../../storage/files') // Example allowed path
        ];
        
        $pathAllowed = false;
        foreach ($allowedPaths as $allowedPath) {
            if ($allowedPath !== false && strpos($realPath, $allowedPath) === 0) {
                $pathAllowed = true;
                break;
            }
        }
        
        if (!$pathAllowed) {
            error_log("Attempted access to disallowed file path: " . $filePath);
            $this->setStatus(403)->setBody('Access denied')->send();
            return;
        }
        
        if (!file_exists($realPath) || !is_readable($realPath)) {
            $this->setStatus(404)->setBody('File not found')->send();
            return;
        }
        
        // Check file size to prevent memory exhaustion
        $fileSize = filesize($realPath);
        if ($fileSize === false || $fileSize > 100 * 1024 * 1024) { // 100MB limit
            $this->setStatus(413)->setBody('File too large')->send();
            return;
        }
        
        $mimeType = mime_content_type($realPath);
        if ($mimeType === false) {
            $mimeType = 'application/octet-stream';
        }
        
        // Sanitize download name
        if ($downloadName !== null) {
            $downloadName = preg_replace('/[^a-zA-Z0-9._-]/', '', basename($downloadName));
            if (empty($downloadName)) {
                $downloadName = 'download';
            }
        } else {
            $downloadName = preg_replace('/[^a-zA-Z0-9._-]/', '', basename($realPath));
            if (empty($downloadName)) {
                $downloadName = 'download';
            }
        }
        
        $this->setHeader('Content-Type', $mimeType);
        $this->setHeader('Content-Length', (string) $fileSize);
        
        $disp = $inline ? 'inline' : 'attachment';
        $this->setHeader('Content-Disposition', $disp . '; filename="' . $downloadName . '"');
        
        // For large files, consider streaming instead of loading into memory
        if ($fileSize > 10 * 1024 * 1024) { // 10MB threshold
            $this->metrics['file']++;
            $this->triggerEvent('file');
            
            $handle = fopen($realPath, 'rb');
            if ($handle === false) {
                $this->setStatus(500)->setBody('Error reading file')->send();
                return;
            }
            
            $this->stream($handle, 8192, true);
        } else {
            $this->setBody(file_get_contents($realPath));
            $this->metrics['file']++;
            $this->triggerEvent('file');
            $this->send();
        }
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
