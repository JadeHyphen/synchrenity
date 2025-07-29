
# Synchrenity WebSocket Server

> Native PHP WebSocket server with authentication, rate limiting, hooks, audit logging, and extensibility.

---

## 🚀 Starting the Server

```php
$ws = $core->websocket;
$ws->start();
```

---

## 🔑 Authentication

```php
$ws->setAuthCallback(function($client, $msg) {
    // Custom auth logic
    return true;
});
```

---

## 🚦 Rate Limiting

```php
$ws->setRateLimiter($core->rateLimiter);
```

---

## 🔄 Hooks & Message Handling

```php
$ws->addHook(function($client, $msg) {
    // Custom message handling
});
```

---

## 🧑‍💻 Example: Broadcast Message

```php
$ws->broadcast('system.announcement', ['text' => 'Hello users!']);
```

---

## 🔗 See Also

- [Usage Guide](USAGE_GUIDE.md)
