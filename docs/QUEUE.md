# Synchrenity Job Queue

## Overview
Async job dispatch, delayed jobs, audit logging, and hooks.

## Usage
```php
$queue = $core->queue;
$queue->dispatch('sendEmail', ['to' => 'user@example.com']);
$queue->on('job.completed', function($job) {
    // Handle completion
});
```
