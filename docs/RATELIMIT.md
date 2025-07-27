# Synchrenity Rate Limiter

## Overview
Sliding window rate limiting for actions, endpoints, and roles. Hooks, audit logging, burst control, and analytics.

## Usage
```php
$rateLimiter = $core->rateLimiter;
$rateLimiter->setLimit('login', 5, 60); // 5 logins per 60s
$allowed = $rateLimiter->check($userId, 'login');

// API Rate Limiter
$apiRateLimiter = $core->apiRateLimiter;
$apiRateLimiter->setLimit('GET:/api/resource', 'user', 50, 60);
$allowed = $apiRateLimiter->check($userId, 'user', 'GET:/api/resource');
```
