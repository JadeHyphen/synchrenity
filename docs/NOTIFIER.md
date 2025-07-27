# Synchrenity Notifier

## Overview
Multi-channel notification system with templating, audit logging, and hooks.

## Usage
```php
$notifier = $core->notifier;
$notifier->send('user@example.com', 'Welcome!', 'welcome_template', ['name' => 'User']);
$notifier->on('notification.sent', function($meta) {
    // Log or react
});
```
