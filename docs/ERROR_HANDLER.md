# Synchrenity Error Handler

The Synchrenity Error Handler provides ultra-robust, extensible error handling with centralized error/exception management, custom error types, secure reporting, hooks/events, and integration with logging, monitoring, and notification systems.

## Table of Contents

- [Overview](#overview)
- [Quick Start](#quick-start)
- [Error Handling](#error-handling)
- [Configuration](#configuration)
- [Custom Error Types](#custom-error-types)
- [Logging & Reporting](#logging--reporting)
- [Hooks & Events](#hooks--events)
- [Rate Limiting](#rate-limiting)
- [Monitoring Integration](#monitoring-integration)
- [Advanced Features](#advanced-features)

## Overview

The Error Handler provides:

- **Centralized error management**: Handle all errors and exceptions from one place
- **Custom error types**: Define application-specific error types
- **Secure error reporting**: Control what error information is exposed
- **Rate limiting**: Prevent error spam and abuse
- **Integration support**: Connect with logging, monitoring, and notification systems
- **Debug & production modes**: Different behavior for development vs production
- **Event hooks**: React to errors with custom logic
- **Context preservation**: Maintain error context for debugging

## Quick Start

```php
use Synchrenity\ErrorHandler\SynchrenityErrorHandler;

// Initialize error handler
$errorHandler = new SynchrenityErrorHandler([
    'logLevel' => 'error',
    'debugMode' => false,
    'logDestination' => 'file'
]);

// Register as global handler
$errorHandler->register();

// Or handle specific errors
try {
    // Some risky operation
    $result = riskyOperation();
} catch (\Exception $e) {
    $errorHandler->handle($e);
}
```

## Error Handling

### Registering Global Handlers

```php
// Register for all PHP errors and exceptions
$errorHandler->register();

// Register specific handlers
$errorHandler->registerErrorHandler();     // PHP errors
$errorHandler->registerExceptionHandler(); // Uncaught exceptions
$errorHandler->registerShutdownHandler();  // Fatal errors
```

### Manual Error Handling

```php
// Handle exceptions
try {
    throw new \Exception('Something went wrong');
} catch (\Exception $e) {
    $errorHandler->handle($e);
}

// Handle custom errors
$errorHandler->handleError(E_USER_ERROR, 'Custom error message', __FILE__, __LINE__);

// Report errors with context
$errorHandler->report('Database connection failed', [
    'host' => 'db.example.com',
    'database' => 'app_prod',
    'user' => 'app_user'
]);
```

### Error Response Management

```php
// Set custom error responses
$errorHandler->setErrorResponse(404, function($error) {
    return [
        'status' => 404,
        'message' => 'Resource not found',
        'error_id' => $error['id']
    ];
});

$errorHandler->setErrorResponse(500, function($error) {
    if ($this->debugMode) {
        return ['message' => $error['message'], 'trace' => $error['trace']];
    }
    return ['message' => 'Internal server error', 'error_id' => $error['id']];
});
```

## Configuration

### Basic Configuration

```php
$config = [
    'logLevel' => 'error',           // 'debug', 'info', 'warning', 'error', 'critical'
    'debugMode' => false,            // Show detailed errors in development
    'logDestination' => 'file',      // 'file', 'syslog', 'database', 'null'
    'maxErrors' => 100,              // Maximum errors per session
    'rateLimitWindow' => 3600,       // Rate limit window in seconds
    'enableStackTrace' => true,      // Include stack traces
    'enableContext' => true,         // Include request/session context
    'notifyOnErrors' => true,        // Send notifications
    'suppressDuplicates' => true     // Suppress duplicate errors
];

$errorHandler = new SynchrenityErrorHandler($config);
```

### Environment-Based Configuration

```php
// Development configuration
if ($env === 'development') {
    $errorHandler->setDebugMode(true);
    $errorHandler->setLogLevel('debug');
    $errorHandler->enableStackTrace(true);
}

// Production configuration
if ($env === 'production') {
    $errorHandler->setDebugMode(false);
    $errorHandler->setLogLevel('error');
    $errorHandler->enableStackTrace(false);
    $errorHandler->setSuppressDuplicates(true);
}

// Testing configuration
if ($env === 'testing') {
    $errorHandler->setLogDestination('null');
    $errorHandler->disableNotifications();
}
```

## Custom Error Types

### Defining Custom Errors

```php
// Register custom error types
$errorHandler->addCustomError('VALIDATION_ERROR', [
    'code' => 1001,
    'message' => 'Data validation failed',
    'level' => 'warning',
    'notify' => false
]);

$errorHandler->addCustomError('DATABASE_ERROR', [
    'code' => 2001,
    'message' => 'Database operation failed',
    'level' => 'error',
    'notify' => true,
    'retry' => true
]);

$errorHandler->addCustomError('AUTH_ERROR', [
    'code' => 3001,
    'message' => 'Authentication failed',
    'level' => 'warning',
    'notify' => false,
    'track_ip' => true
]);
```

### Using Custom Errors

```php
// Trigger custom errors
$errorHandler->triggerCustomError('VALIDATION_ERROR', [
    'field' => 'email',
    'value' => 'invalid-email',
    'rule' => 'email'
]);

$errorHandler->triggerCustomError('DATABASE_ERROR', [
    'query' => 'SELECT * FROM users',
    'error' => 'Connection timeout',
    'host' => 'db.example.com'
]);

// Check if error type exists
if ($errorHandler->hasCustomError('PAYMENT_ERROR')) {
    // Handle payment-specific error
}
```

### Error Classification

```php
// Classify errors by severity
$errorHandler->setSeverityLevels([
    'low' => ['VALIDATION_ERROR', 'USER_INPUT_ERROR'],
    'medium' => ['API_ERROR', 'CACHE_ERROR'],
    'high' => ['DATABASE_ERROR', 'SERVICE_ERROR'],
    'critical' => ['SECURITY_ERROR', 'DATA_CORRUPTION']
]);

// Get errors by severity
$criticalErrors = $errorHandler->getErrorsBySeverity('critical');
```

## Logging & Reporting

### File Logging

```php
// Configure file logging
$errorHandler->setLogDestination('file');
$errorHandler->setLogFile('/var/log/app/errors.log');
$errorHandler->setLogRotation(true, '10MB', 30); // Rotate at 10MB, keep 30 files

// Custom log format
$errorHandler->setLogFormat('[{timestamp}] {level}: {message} {context}');
```

### Database Logging

```php
// Configure database logging
$errorHandler->setLogDestination('database');
$errorHandler->setLogTable('error_logs');
$errorHandler->setDatabaseConnection($pdo);

// Custom database schema
$errorHandler->setLogSchema([
    'id' => 'auto_increment',
    'error_id' => 'varchar(64)',
    'level' => 'varchar(20)',
    'message' => 'text',
    'context' => 'json',
    'stack_trace' => 'text',
    'created_at' => 'timestamp'
]);
```

### External Reporting

```php
// Sentry integration
$errorHandler->addReporter('sentry', function($error) {
    \Sentry\captureException($error['exception']);
});

// Slack notifications
$errorHandler->addReporter('slack', function($error) {
    $webhook->send([
        'text' => "Error: {$error['message']}",
        'attachments' => [
            [
                'color' => 'danger',
                'fields' => [
                    ['title' => 'File', 'value' => $error['file']],
                    ['title' => 'Line', 'value' => $error['line']]
                ]
            ]
        ]
    ]);
});

// Email notifications
$errorHandler->addReporter('email', function($error) {
    $this->mailer->send([
        'to' => 'admin@example.com',
        'subject' => 'Application Error Alert',
        'body' => $this->formatErrorEmail($error)
    ]);
});
```

## Hooks & Events

### Error Lifecycle Hooks

```php
// Before error handling
$errorHandler->onBeforeHandle(function($error) {
    // Log to external service
    // Modify error data
    return $error;
});

// After error handling
$errorHandler->onAfterHandle(function($error, $response) {
    // Update metrics
    // Send notifications
    // Clean up resources
});

// On error suppression
$errorHandler->onSuppress(function($error, $reason) {
    // Log suppression reason
    // Update statistics
});
```

### Event-Driven Error Handling

```php
// Register event listeners
$errorHandler->addEventListener('error.database', function($error) {
    // Database-specific error handling
    $this->metrics->increment('database.errors');
    $this->alerting->notifyDbAdmin($error);
});

$errorHandler->addEventListener('error.security', function($error) {
    // Security-specific error handling
    $this->security->logSecurityEvent($error);
    $this->alerting->notifySecurityTeam($error);
});

$errorHandler->addEventListener('error.user', function($error) {
    // User-facing error handling
    $this->feedback->collectUserFeedback($error);
});
```

## Rate Limiting

### Basic Rate Limiting

```php
// Global rate limiting
$errorHandler->setRateLimit(100, 3600); // 100 errors per hour

// IP-based rate limiting
$errorHandler->setIpRateLimit(10, 600); // 10 errors per 10 minutes per IP

// Session-based rate limiting
$errorHandler->setSessionRateLimit(5, 300); // 5 errors per 5 minutes per session
```

### Advanced Rate Limiting

```php
// Error-type specific rate limiting
$errorHandler->setCustomRateLimit('VALIDATION_ERROR', 50, 3600);
$errorHandler->setCustomRateLimit('DATABASE_ERROR', 5, 600);

// Burst rate limiting
$errorHandler->setBurstRateLimit(10, 60); // Allow 10 errors in 1 minute burst

// Rate limit by user
$errorHandler->setUserRateLimit($userId, 20, 3600);

// Check rate limits
if ($errorHandler->isRateLimited($error)) {
    // Handle rate limited error
    return $errorHandler->handleRateLimited($error);
}
```

## Monitoring Integration

### Metrics Collection

```php
// Built-in metrics
$metrics = $errorHandler->getMetrics();
/*
[
    'handled' => 245,
    'rate_limited' => 12,
    'notified' => 38,
    'errors' => 245,
    'by_type' => [
        'VALIDATION_ERROR' => 120,
        'DATABASE_ERROR' => 25,
        'API_ERROR' => 100
    ],
    'by_severity' => [
        'low' => 120,
        'medium' => 100,
        'high' => 20,
        'critical' => 5
    ]
]
*/

// Custom metrics
$errorHandler->incrementMetric('custom.payment_errors');
$errorHandler->setMetric('response.time', 250);
```

### Health Checks

```php
// Health check integration
$errorHandler->addHealthCheck('error_rate', function() {
    $recentErrors = $this->getErrorsInTimeWindow(300); // Last 5 minutes
    return count($recentErrors) < 10 ? 'healthy' : 'unhealthy';
});

$errorHandler->addHealthCheck('error_storage', function() {
    return $this->canWriteToLog() ? 'healthy' : 'unhealthy';
});
```

### Performance Monitoring

```php
// Track error handling performance
$errorHandler->enablePerformanceTracking();

// Performance metrics
$performance = $errorHandler->getPerformanceMetrics();
/*
[
    'avg_handling_time' => 25.5,  // milliseconds
    'max_handling_time' => 150.0,
    'total_handling_time' => 12750.0,
    'memory_usage' => 2048576    // bytes
]
*/
```

## Advanced Features

### Error Context Enrichment

```php
// Add context providers
$errorHandler->addContextProvider('request', function() {
    return [
        'url' => $_SERVER['REQUEST_URI'] ?? '',
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];
});

$errorHandler->addContextProvider('user', function() {
    return [
        'id' => $this->auth->getUserId(),
        'email' => $this->auth->getUserEmail(),
        'role' => $this->auth->getUserRole()
    ];
});

$errorHandler->addContextProvider('system', function() {
    return [
        'memory_usage' => memory_get_usage(true),
        'peak_memory' => memory_get_peak_usage(true),
        'load_average' => sys_getloadavg()
    ];
});
```

### Error Aggregation

```php
// Group similar errors
$errorHandler->enableErrorAggregation([
    'group_by' => ['message', 'file', 'line'],
    'time_window' => 3600,
    'max_occurrences' => 100
]);

// Get aggregated errors
$aggregated = $errorHandler->getAggregatedErrors();
/*
[
    'hash123' => [
        'message' => 'Database connection failed',
        'first_occurrence' => '2024-01-01 10:00:00',
        'last_occurrence' => '2024-01-01 10:15:00',
        'count' => 25,
        'representative_error' => [...],
        'contexts' => [...]
    ]
]
*/
```

### Error Recovery

```php
// Automatic error recovery
$errorHandler->addRecoveryStrategy('DATABASE_ERROR', function($error) {
    // Try reconnecting to database
    if ($this->database->reconnect()) {
        return ['recovered' => true, 'action' => 'reconnected'];
    }
    return ['recovered' => false];
});

$errorHandler->addRecoveryStrategy('CACHE_ERROR', function($error) {
    // Clear cache and retry
    $this->cache->clear();
    return ['recovered' => true, 'action' => 'cache_cleared'];
});

// Circuit breaker pattern
$errorHandler->addCircuitBreaker('external_api', [
    'failure_threshold' => 5,
    'recovery_timeout' => 60,
    'test_request_timeout' => 10
]);
```

## Configuration Examples

### Production Configuration

```php
$productionConfig = [
    'logLevel' => 'error',
    'debugMode' => false,
    'logDestination' => 'database',
    'enableStackTrace' => false,
    'enableContext' => false,
    'notifyOnErrors' => true,
    'suppressDuplicates' => true,
    'maxErrors' => 50,
    'rateLimitWindow' => 3600,
    'reporters' => ['sentry', 'email'],
    'healthChecks' => true,
    'metrics' => true
];
```

### Development Configuration

```php
$developmentConfig = [
    'logLevel' => 'debug',
    'debugMode' => true,
    'logDestination' => 'file',
    'enableStackTrace' => true,
    'enableContext' => true,
    'notifyOnErrors' => false,
    'suppressDuplicates' => false,
    'maxErrors' => 1000,
    'rateLimitWindow' => 3600,
    'reporters' => ['file'],
    'healthChecks' => false,
    'metrics' => false
];
```

## Best Practices

1. **Use appropriate log levels** for different environments
2. **Implement rate limiting** to prevent error spam
3. **Add context** to help with debugging
4. **Use custom error types** for application-specific errors
5. **Monitor error metrics** and set up alerts
6. **Test error handling** in different scenarios
7. **Sanitize sensitive data** before logging
8. **Implement error recovery** where possible
9. **Use structured logging** for better analysis
10. **Document error codes** and their meanings