# Synchrenity WebSocket Server

## Overview
Native PHP WebSocket server with audit logging, authentication, rate limiting, and hooks.

## Usage
```php
$ws = $core->websocket;
$ws->setAuthCallback(function($client, $msg) {
    // Custom auth logic
    return true;
});
$ws->setRateLimiter($core->rateLimiter);
$ws->addHook(function($client, $msg) {
    // Custom message handling
});
$ws->start();
```
