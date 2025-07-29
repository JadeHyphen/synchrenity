<?php
namespace Synchrenity\Mailer;

/**
 * SynchrenityMailer: Secure, robust, and extensible mailer for Synchrenity
 * Features: SMTP, sendmail, API integration, templating, attachments, logging, rate limiting, event hooks, encryption, error handling.
 */
class SynchrenityMailer
{
    protected $transport;
    protected $config;
    protected $logger;
    protected $rateLimit = 100; // emails/hour
    protected $sentCount = 0;
    protected $lastReset;
    protected $eventHooks = [];
    protected $plugins = [];
    protected $queue = [];
    protected $metrics = [
        'sent' => 0,
        'failed' => 0,
        'queued' => 0,
        'attachments' => 0,
        'last_error' => null
    ];
    protected $encryptionKey = null;
    protected $multiTenant = false;
    protected $tenantId = null;
    protected $templateEngine = null;

    public function __construct($config = [])
    {
        $this->config = $config;
        $this->transport = $config['transport'] ?? 'smtp';
        $this->logger = $config['logger'] ?? null;
        $this->rateLimit = $config['rate_limit'] ?? 100;
        $this->encryptionKey = $config['encryption_key'] ?? null;
        $this->multiTenant = $config['multi_tenant'] ?? false;
        $this->tenantId = $config['tenant_id'] ?? null;
        $this->templateEngine = $config['template_engine'] ?? null;
        $this->lastReset = time();
    }

    /**
     * Send an email (advanced, supports SMTP, sendmail, API, attachments, templates, encryption, hooks, plugins)
     */
    public function send($to, $subject, $body, $headers = [], $attachments = [], $options = [])
    {
        if ($this->isRateLimited()) {
            $this->log('Rate limit exceeded');
            $this->metrics['failed']++;
            $this->metrics['last_error'] = 'Rate limit exceeded';
            $this->triggerEvent('rate_limited', compact('to','subject','body','headers','attachments','options'));
            return false;
        }
        // Template rendering
        if ($this->templateEngine && isset($options['template'])) {
            $body = $this->templateEngine->render($options['template'], $options['template_vars'] ?? []);
        }
        // Encryption (optional)
        if ($this->encryptionKey && !empty($options['encrypt'])) {
            $body = $this->encrypt($body);
        }
        // Pre-send hooks
        $this->triggerEvent('before_send', compact('to','subject','body','headers','attachments','options'));
        // Plugin hooks
        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'beforeSend'])) {
                $plugin->beforeSend($this, $to, $subject, $body, $headers, $attachments, $options);
            }
        }
        // Transport selection
        $success = false;
        try {
            switch ($this->transport) {
                case 'smtp':
                    $success = $this->sendSMTP($to, $subject, $body, $headers, $attachments, $options);
                    break;
                case 'sendmail':
                    $success = $this->sendSendmail($to, $subject, $body, $headers, $attachments, $options);
                    break;
                case 'api':
                    $success = $this->sendAPI($to, $subject, $body, $headers, $attachments, $options);
                    break;
                default:
                    $success = $this->sendBasic($to, $subject, $body, $headers, $attachments);
            }
        } catch (\Throwable $e) {
            $this->log('Send failed: ' . $e->getMessage());
            $this->metrics['failed']++;
            $this->metrics['last_error'] = $e->getMessage();
            $this->triggerEvent('send_failed', ['error'=>$e->getMessage(),'to'=>$to]);
            return false;
        }
        if ($success) {
            $this->sentCount++;
            $this->metrics['sent']++;
            $this->metrics['attachments'] += count($attachments);
            $this->log("Sent mail to $to: $subject");
            $this->triggerEvent('after_send', compact('to','subject','body','headers','attachments','options'));
            foreach ($this->plugins as $plugin) {
                if (is_callable([$plugin, 'afterSend'])) {
                    $plugin->afterSend($this, $to, $subject, $body, $headers, $attachments, $options);
                }
            }
        } else {
            $this->metrics['failed']++;
            $this->metrics['last_error'] = 'Unknown failure';
            $this->triggerEvent('send_failed', ['to'=>$to]);
        }
        return $success;
    }

    // Basic PHP mail fallback
    protected function sendBasic($to, $subject, $body, $headers = [], $attachments = []) {
        $headersStr = '';
        foreach ($headers as $k => $v) {
            $headersStr .= "$k: $v\r\n";
        }
        // Attachments not supported in basic mail()
        return mail($to, $subject, $body, $headersStr);
    }

    // SMTP (stub, replace with PHPMailer or Symfony Mailer for production)
    protected function sendSMTP($to, $subject, $body, $headers = [], $attachments = [], $options = []) {
        // For demo: fallback to basic
        return $this->sendBasic($to, $subject, $body, $headers, $attachments);
    }

    // Sendmail (stub)
    protected function sendSendmail($to, $subject, $body, $headers = [], $attachments = [], $options = []) {
        // For demo: fallback to basic
        return $this->sendBasic($to, $subject, $body, $headers, $attachments);
    }

    // API (stub)
    protected function sendAPI($to, $subject, $body, $headers = [], $attachments = [], $options = []) {
        // For demo: fallback to basic
        return $this->sendBasic($to, $subject, $body, $headers, $attachments);
    }

    // Async queueing
    public function queue($to, $subject, $body, $headers = [], $attachments = [], $options = []) {
        $this->queue[] = compact('to','subject','body','headers','attachments','options');
        $this->metrics['queued']++;
        $this->triggerEvent('queued', end($this->queue));
    }
    public function processQueue($limit = 10) {
        $processed = 0;
        while ($this->queue && $processed < $limit) {
            $job = array_shift($this->queue);
            $this->send($job['to'], $job['subject'], $job['body'], $job['headers'], $job['attachments'], $job['options']);
            $processed++;
        }
        return $processed;
    }

    // Plugin system
    public function registerPlugin($plugin) {
        $this->plugins[] = $plugin;
    }

    // Event hooks
    public function on($event, callable $cb) {
        $this->eventHooks[$event][] = $cb;
    }
    protected function triggerEvent($event, $data = null) {
        foreach ($this->eventHooks[$event] ?? [] as $cb) call_user_func($cb, $data, $this);
    }

    // Encryption (simple demo)
    protected function encrypt($text) {
        if (!$this->encryptionKey) return $text;
        return base64_encode(openssl_encrypt($text, 'aes-256-cbc', $this->encryptionKey, 0, substr(hash('sha256', $this->encryptionKey), 0, 16)));
    }
    protected function decrypt($text) {
        if (!$this->encryptionKey) return $text;
        return openssl_decrypt(base64_decode($text), 'aes-256-cbc', $this->encryptionKey, 0, substr(hash('sha256', $this->encryptionKey), 0, 16));
    }

    // Multi-tenant support
    public function setTenant($tenantId) { $this->tenantId = $tenantId; }
    public function getTenant() { return $this->tenantId; }

    // Metrics
    public function getMetrics() { return $this->metrics; }

    // Advanced logging
    public function setLogger($logger) { $this->logger = $logger; }

    // Set template engine
    public function setTemplateEngine($engine) { $this->templateEngine = $engine; }

    /**
     * Check rate limit
     */
    protected function isRateLimited()
    {
        if (time() - $this->lastReset > 3600) {
            $this->sentCount = 0;
            $this->lastReset = time();
        }
        return $this->sentCount >= $this->rateLimit;
    }

    /**
     * Log mail events
     */
    protected function log($msg)
    {
        if ($this->logger && is_callable($this->logger)) {
            call_user_func($this->logger, $msg, $this);
        } else {
            error_log("[MAILER] $msg");
        }
    }
}
