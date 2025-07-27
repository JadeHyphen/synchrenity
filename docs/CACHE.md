# Synchrenity Cache Manager

## Overview
Multi-backend cache (memory, file) with TTL, audit logging, and hooks.

## Usage
```php
$cache = $core->cache;
$cache->set('key', 'value', 60); // 60s TTL
$value = $cache->get('key');
$cache->delete('key');
$cache->exists('key');
$cache->clear();
```

## Audit
```php
$logs = $core->audit()->getLogs(10);
```
