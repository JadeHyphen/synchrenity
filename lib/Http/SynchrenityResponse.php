<?php
namespace Synchrenity\Http;

/**
 * Synchrenity Http Response: Flexible, secure, feature-rich
 */
class SynchrenityResponse
{
    protected $status = 200;
    protected $headers = [];
    protected $body = '';

    public function __construct($body = '', $status = 200, array $headers = [])
    {
        $this->body = $body;
        $this->status = $status;
        $this->headers = $headers;
    }

    public function setHeader($name, $value)
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    public function setBody($body)
    {
        $this->body = $body;
        return $this;
    }

    public function send()
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }

    public function json($data, $status = 200, $headers = [])
    {
        $this->setHeader('Content-Type', 'application/json');
        $this->setStatus($status);
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
        $this->setBody(json_encode($data));
        $this->send();
    }

    public function file($filePath, $downloadName = null)
    {
        if (!file_exists($filePath)) {
            $this->setStatus(404)->setBody('File not found')->send();
            return;
        }
        $this->setHeader('Content-Type', mime_content_type($filePath));
        if ($downloadName) {
            $this->setHeader('Content-Disposition', 'attachment; filename="' . basename($downloadName) . '"');
        }
        $this->setBody(file_get_contents($filePath));
        $this->send();
    }

    public function stream($resource, $chunkSize = 8192)
    {
        while (!feof($resource)) {
            echo fread($resource, $chunkSize);
        }
        fclose($resource);
    }

    public function setCookie($name, $value, $expire = 0, $secure = true, $httpOnly = true, $sameSite = 'Lax')
    {
        $params = [
            'expires' => $expire,
            'path' => '/',
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite
        ];
        setcookie($name, $value, $params);
    }

    public function securityHeaders()
    {
        $this->setHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->setHeader('X-Content-Type-Options', 'nosniff');
        $this->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->setHeader('Content-Security-Policy', "default-src 'self';");
        $this->setHeader('X-XSS-Protection', '1; mode=block');
    }
}
