
# Synchrenity Validator

> Rule-based validation, hooks, audit logging, and extensibility.

---

## ✅ Adding Rules

```php
$validator = $core->validator;
$validator->addRule('email', function($value) {
    return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
});
```

---

## 🧪 Validating Data

```php
$valid = $validator->validate('email', 'user@example.com');
```

---

## 🔄 Hooks & Events

```php
$validator->on('validation.failed', function($field, $value) {
    // Log or alert
});
```

---

## 🧑‍💻 Example: Custom Validator

```php
$validator->addRule('strong_password', function($value) {
    return strlen($value) > 12 && preg_match('/[A-Z]/', $value);
});
```

---

## 🔗 See Also

- [Usage Guide](USAGE_GUIDE.md)
