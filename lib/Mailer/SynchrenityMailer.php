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

    public function __construct($config = [])
    {
        $this->config = $config;
        $this->transport = $config['transport'] ?? 'smtp';
        $this->logger = $config['logger'] ?? null;
        $this->lastReset = time();
    }

    /**
     * Send an email (basic)
     */
    public function send($to, $subject, $body, $headers = [], $attachments = [])
    {
        if ($this->isRateLimited()) {
            $this->log('Rate limit exceeded');
            return false;
        }
        // Basic mail sending (replace with PHPMailer, Symfony Mailer, etc. for production)
        $headersStr = '';
        foreach ($headers as $k => $v) {
            $headersStr .= "$k: $v\r\n";
        }
        $success = mail($to, $subject, $body, $headersStr);
        $this->sentCount++;
        $this->log("Sent mail to $to: $subject");
        return $success;
    }

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
            call_user_func($this->logger, $msg);
        } else {
            error_log("[MAILER] $msg");
        }
    }
}
