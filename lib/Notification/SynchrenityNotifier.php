<?php

declare(strict_types=1);

namespace Synchrenity\Notification;

class SynchrenityNotifier
{
    protected $auditTrail;
    protected $channels = [];
    protected $hooks    = [];

    public function setAuditTrail($auditTrail)
    {
        $this->auditTrail = $auditTrail;
    }

    // Register a notification channel
    public function registerChannel($type, callable $handler)
    {
        $this->channels[$type] = $handler;
    }

    // Add custom hook (e.g., logging, analytics)
    public function addHook(callable $hook)
    {
        $this->hooks[] = $hook;
    }

    // Send notification
    public function send($type, $recipient, $message, $params = [])
    {
        $status = 'pending';
        $error  = null;

        // Interpolate parameters
        if (!empty($params)) {
            foreach ($params as $k => $v) {
                $message = str_replace('{' . $k . '}', $v, $message);
            }
        }

        if (isset($this->channels[$type]) && is_callable($this->channels[$type])) {
            try {
                $result = call_user_func($this->channels[$type], $recipient, $message, $params);
                $status = $result === true ? 'sent' : ($result ?: 'failed');
            } catch (\Exception $ex) {
                $status = 'error';
                $error  = $ex->getMessage();
            }
        } else {
            $status = 'no_channel';
            $error  = 'Channel not registered';
        }
        $meta = [
            'type'      => $type,
            'recipient' => $recipient,
            'message'   => $message,
            'params'    => $params,
            'status'    => $status,
            'error'     => $error,
        ];

        foreach ($this->hooks as $hook) {
            call_user_func($hook, $meta);
        }

        if ($this->auditTrail) {
            $this->auditTrail->log('send_notification', $meta, null);
        }

        return $status === 'sent';
    }
}
