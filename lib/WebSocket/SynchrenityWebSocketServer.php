namespace Synchrenity\WebSocket;

class SynchrenityWebSocketServer {
    protected $host = '0.0.0.0';
    protected $port = 8080;
    protected $ssl = false;
    protected $certFile;
    protected $keyFile;
    protected $auditTrail;
    protected $clients = [];
    protected $authCallback;
    protected $rateLimiter;
    protected $hooks = [];

    public function __construct($host = '0.0.0.0', $port = 8080, $ssl = false, $certFile = null, $keyFile = null) {
        $this->host = $host;
        $this->port = $port;
        $this->ssl = $ssl;
        $this->certFile = $certFile;
        $this->keyFile = $keyFile;
    }

    public function setAuditTrail($auditTrail) {
        $this->auditTrail = $auditTrail;
    }
    public function setAuthCallback(callable $callback) {
        $this->authCallback = $callback;
    }
    public function setRateLimiter($rateLimiter) {
        $this->rateLimiter = $rateLimiter;
    }
    public function addHook(callable $hook) {
        $this->hooks[] = $hook;
    }

    public function start() {
        $context = null;
        if ($this->ssl) {
            $context = stream_context_create([
                'ssl' => [
                    'local_cert' => $this->certFile,
                    'local_pk' => $this->keyFile,
                    'allow_self_signed' => true,
                    'verify_peer' => false
                ]
            ]);
            $server = stream_socket_server("ssl://{$this->host}:{$this->port}", $errno, $errstr, STREAM_SERVER_BIND|STREAM_SERVER_LISTEN, $context);
        } else {
            $server = stream_socket_server("tcp://{$this->host}:{$this->port}", $errno, $errstr);
        }
        if (!$server) {
            throw new \Exception("WebSocket server failed: $errstr ($errno)");
        }
        $this->audit('server_start', null, ['host'=>$this->host,'port'=>$this->port,'ssl'=>$this->ssl]);
        stream_set_blocking($server, false);
        $this->clients = [];
        while (true) {
            $read = array_merge([$server], $this->clients);
            $write = $except = [];
            if (stream_select($read, $write, $except, 1) > 0) {
                if (in_array($server, $read)) {
                    $client = stream_socket_accept($server, 0);
                    if ($client) {
                        $this->handshake($client);
                        $this->clients[] = $client;
                        $this->audit('client_connect', null, ['ip'=>stream_socket_get_name($client,true)]);
                    }
                    unset($read[array_search($server, $read)]);
                }
                foreach ($read as $client) {
                    $data = fread($client, 2048);
                    if ($data === '' || $data === false) {
                        $this->disconnect($client);
                        continue;
                    }
                    $msg = $this->decode($data);
                    $ip = stream_socket_get_name($client,true);
                    // Auth check
                    if ($this->authCallback && !$this->authCallback($client, $msg)) {
                        $this->audit('auth_fail', null, ['ip'=>$ip,'msg'=>$msg]);
                        $this->disconnect($client);
                        continue;
                    }
                    // Rate limit
                    if ($this->rateLimiter && !$this->rateLimiter->check($ip, 'ws_message')) {
                        $this->audit('rate_limit', null, ['ip'=>$ip]);
                        continue;
                    }
                    foreach ($this->hooks as $hook) {
                        call_user_func($hook, $client, $msg);
                    }
                    $this->audit('ws_message', null, ['ip'=>$ip,'msg'=>$msg]);
                    $this->broadcast($msg, $client);
                }
            }
            // Heartbeat/ping-pong (optional)
        }
    }

    protected function handshake($client) {
        $headers = '';
        while ($line = fgets($client)) {
            $headers .= $line;
            if (rtrim($line) === '') break;
        }
        if (preg_match('/Sec-WebSocket-Key: (.*)\r\n/', $headers, $matches)) {
            $key = trim($matches[1]);
            $accept = base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
            $response = "HTTP/1.1 101 Switching Protocols\r\n" .
                "Upgrade: websocket\r\n" .
                "Connection: Upgrade\r\n" .
                "Sec-WebSocket-Accept: $accept\r\n\r\n";
            fwrite($client, $response);
        }
    }

    protected function decode($data) {
        // Minimal frame decode (for text frames)
        $bytes = array_map('ord', str_split($data));
        $len = $bytes[1] & 127;
        $mask = $len === 126 ? 4 : ($len === 127 ? 10 : 2);
        $masks = array_slice($bytes, $mask, 4);
        $msg = '';
        for ($i = $mask + 4; $i < count($bytes); ++$i) {
            $msg .= chr($bytes[$i] ^ $masks[($i - $mask - 4) % 4]);
        }
        return $msg;
    }

    public function broadcast($msg, $exclude = null) {
        $frame = $this->encode($msg);
        foreach ($this->clients as $client) {
            if ($client !== $exclude) {
                @fwrite($client, $frame);
            }
        }
    }

    protected function encode($msg) {
        $len = strlen($msg);
        $frame = chr(129);
        if ($len <= 125) {
            $frame .= chr($len);
        } elseif ($len <= 65535) {
            $frame .= chr(126) . pack('n', $len);
        } else {
            $frame .= chr(127) . pack('J', $len);
        }
        $frame .= $msg;
        return $frame;
    }

    protected function disconnect($client) {
        $ip = stream_socket_get_name($client,true);
        $this->audit('client_disconnect', null, ['ip'=>$ip]);
        fclose($client);
        $this->clients = array_filter($this->clients, function($c) use ($client) { return $c !== $client; });
    }

    protected function audit($action, $userId = null, $meta = []) {
        if ($this->auditTrail) {
            $this->auditTrail->log($action, [], $userId, $meta);
        }
    }
}
