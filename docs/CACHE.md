
# Synchrenity Cache Manager

> Multi-backend cache (memory, file, etc), TTL, hooks, audit logging, and extensibility.

---

## 🗄️ Basic Usage

```php
$cache = $core->cache;
$cache->set('key', 'value', 60); // 60s TTL
$value = $cache->get('key');
$cache->delete('key');
$cache->exists('key');
$cache->clear();
```

---

## 🔄 Hooks & Events

```php
$cache->on('cache.miss', function($key) {
    // Log or fetch from DB
});
```

---

## 🧑‍💻 Example: Custom Backend

```php
$cache->setBackend(new MyCustomCacheBackend());
```

---

## 🔗 See Also

- [Audit Trail](AUDIT.md)
- [Usage Guide](USAGE_GUIDE.md)
