
# Synchrenity Notifier

> Multi-channel notifications, templates, hooks, audit logging, and extensibility.

---

## ✉️ Sending Notifications

```php
$notifier = $core->notifier;
$notifier->send('user@example.com', 'Welcome!', 'welcome_template', ['name' => 'User']);
```

---

## 🔄 Hooks & Events

```php
$notifier->on('notification.sent', function($meta) {
    // Log or react
});
```

---

## 🧑‍💻 Example: Custom Channel

```php
$notifier->addChannel('sms', function($to, $message) {
    // Send SMS
});
```

---

## 🔗 See Also

- [Usage Guide](USAGE_GUIDE.md)
