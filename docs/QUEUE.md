
# Synchrenity Job Queue

> Async job dispatch, delayed jobs, hooks, audit logging, and extensibility.

---

## 🚀 Dispatching Jobs

```php
$queue = $core->queue;
$queue->dispatch('sendEmail', ['to' => 'user@example.com']);
```

---

## ⏳ Delayed & Scheduled Jobs

```php
$queue->dispatch('generateReport', ['type' => 'monthly'], delay: 300); // 5 min delay
```

---

## 🔄 Hooks & Events

```php
$queue->on('job.completed', function($job) {
    // Handle completion
});
$queue->on('job.failed', function($job, $error) {
    // Handle failure
});
```

---

## 🧑‍💻 Example: Custom Job Handler

```php
$queue->registerHandler('resizeImage', function($payload) {
    // Custom logic
});
```

---

## 🔗 See Also

- [Audit Trail](AUDIT.md)
- [Usage Guide](USAGE_GUIDE.md)
