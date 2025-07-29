
# Synchrenity Rate Limiter

> Sliding window, burst control, analytics, hooks, and audit for endpoints, actions, and roles.

---

## 🚦 Basic Usage

```php
$rateLimiter = $core->rateLimiter;
$rateLimiter->setLimit('login', 5, 60); // 5 logins per 60s
if (!$rateLimiter->check($userId, 'login')) {
    throw new Exception('Too many login attempts.');
}
```

---

## 🌐 API Rate Limiting

```php
$apiRateLimiter = $core->apiRateLimiter;
$apiRateLimiter->setLimit('GET:/api/resource', 'user', 50, 60);
if (!$apiRateLimiter->check($userId, 'user', 'GET:/api/resource')) {
    // Deny or delay
}
```

---

## 📈 Analytics & Burst Control

- Real-time metrics and logs
- Burst control and dynamic limits
- Audit all rate limit events ([Audit Trail](AUDIT.md))

---

## 🧑‍💻 Example: Custom Hook

```php
$rateLimiter->on('limit.exceeded', function($meta) {
    // Alert or log
});
```

---

## 🔗 See Also

- [Audit Trail](AUDIT.md)
- [Usage Guide](USAGE_GUIDE.md)
