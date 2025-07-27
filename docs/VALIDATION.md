# Synchrenity Validator

## Overview
Rule-based validation, audit logging, and hooks.

## Usage
```php
$validator = $core->validator;
$validator->addRule('email', function($value) {
    return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
});
$valid = $validator->validate('email', 'user@example.com');
```
