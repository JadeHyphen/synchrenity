# Synchrenity Core

## Overview
SynchrenityCore is the heart of the framework, responsible for configuration, environment, module registration, lifecycle hooks, error handling, and request dispatching.

## Initialization
```php
$config = require __DIR__.'/../config/app.php';
$core = new \Synchrenity\SynchrenityCore($config);
```

## Module Registration
Modules are auto-registered and available as properties:
- `$core->auth` (Auth)
- `$core->cache` (CacheManager)
- `$core->rateLimiter` (RateLimiter)
- `$core->notifier` (Notifier)
- `$core->media` (MediaManager)
- `$core->plugin` (PluginManager)
- `$core->queue` (JobQueue)
- `$core->i18n` (I18nManager)
- `$core->websocket` (WebSocketServer)
- `$core->validator` (Validator)
- `$core->audit()` (AuditTrail)

## Lifecycle Hooks
```php
$core->onLifecycle('boot', function($core) {
    // Custom boot logic
});
$core->onLifecycle('shutdown', function($core) {
    // Cleanup
});
```

## Error Handling
```php
$core->setErrorHandler(function($e) {
    // Custom error reporting
});
```

## Request Handling
```php
$core->handleRequest();
```

## CLI Integration
```php
$core->runCli($argv);
```

## Events
```php
$core->on('user.registered', function($user) {
    // Send welcome email
});
$core->dispatch('user.registered', $user);
```
