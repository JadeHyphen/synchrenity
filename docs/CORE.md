
# Synchrenity Core System

> The foundation of every Synchrenity application: lifecycle, modules, events, error handling, CLI, and extensibility.

---

## 🏗️ Initialization & Bootstrapping

```php
require __DIR__.'/vendor/autoload.php';
$config = require __DIR__.'/config/app.php';
$core = new \Synchrenity\SynchrenityCore($config);
```

---

## 🧩 Module Registration & Access

All core modules are auto-registered and available as properties:

- `$core->auth` — [Authentication & RBAC](AUTH.md)
- `$core->cache` — [Cache Manager](CACHE.md)
- `$core->rateLimiter` — [Rate Limiter](RATELIMIT.md)
- `$core->notifier` — [Notifier](NOTIFIER.md)
- `$core->media` — [Media Manager](MEDIA.md)
- `$core->plugin` — [Plugin System](PLUGIN.md)
- `$core->queue` — [Job Queue](QUEUE.md)
- `$core->i18n` — [I18n & Localization](I18N.md)
- `$core->websocket` — [WebSocket Server](WEBSOCKET.md)
- `$core->validator` — [Validation](VALIDATION.md)
- `$core->audit()` — [Audit Trail](AUDIT.md)

---

## 🔄 Lifecycle Hooks & Events

Register hooks for any lifecycle stage:

```php
$core->onLifecycle('boot', function($core) {
    // Custom boot logic
});
$core->onLifecycle('shutdown', function($core) {
    // Cleanup
});
```

### Event System

```php
$core->on('user.registered', function($user) {
    // Send welcome email
});
$core->dispatch('user.registered', $user);
```

---

## 🛡️ Error Handling & Observability

Custom error handler:

```php
$core->setErrorHandler(function($e) {
    // Custom error reporting, logging, or alerting
});
```

Built-in integration with [Monitoring & Logging](MONITORING.md).

---

## 🌐 Request Handling & Routing

```php
$core->handleRequest();
```

---

## 🖥️ CLI Integration

```php
$core->runCli($argv);
```

---

## ⚡ Advanced Usage & Extensibility

- Register custom modules and plugins via `$core->plugin->register()`
- Add new event types and listeners
- Hot-reload config and modules
- Introspect runtime state for debugging

---

## 🧑‍💻 Example: Custom Module

```php
class MyModule {
    public function doSomething() { /* ... */ }
}
$core->plugin->register('myModule', new MyModule());
$core->myModule->doSomething();
```

---

## 🔗 See Also

- [Usage Guide](USAGE_GUIDE.md)
- [API Reference](API.md)
