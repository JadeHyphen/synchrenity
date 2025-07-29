
# Synchrenity Plugin System

> Register, activate, and extend Synchrenity with plugins, hooks, and audit logging.

---

## 🧩 Registering Plugins

```php
$plugin = $core->plugin;
$plugin->register('MyPlugin', $pluginInstance);
```

---

## 🚦 Activating & Deactivating

```php
$plugin->activate('MyPlugin');
$plugin->deactivate('MyPlugin');
```

---

## 🔄 Hooks & Events

```php
$plugin->on('plugin.activated', function($meta) {
    // Custom logic
});
```

---

## 🧑‍💻 Example: Custom Plugin

```php
class MyPlugin {
    public function boot() { /* ... */ }
}
$core->plugin->register('myPlugin', new MyPlugin());
$core->plugin->activate('myPlugin');
```

---

## 🔗 See Also

- [Usage Guide](USAGE_GUIDE.md)
