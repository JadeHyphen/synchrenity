# Synchrenity Tenant Manager

The Synchrenity Tenant Manager provides comprehensive multi-tenancy support for SaaS applications, including tenant isolation, configuration management, quotas, billing, and security.

## Table of Contents

- [Overview](#overview)
- [Quick Start](#quick-start)
- [Tenant Registration](#tenant-registration)
- [Tenant Context Management](#tenant-context-management)
- [Configuration & Isolation](#configuration--isolation)
- [Quotas & Billing](#quotas--billing)
- [Security & Access Control](#security--access-control)
- [Advanced Features](#advanced-features)

## Overview

The Tenant Manager enables you to:

- **Multi-tenant architecture**: Isolate data and configuration per tenant
- **Dynamic tenant switching**: Change tenant context at runtime
- **Resource quotas**: Set limits per tenant (storage, users, API calls)
- **Billing integration**: Track usage and generate billing data
- **Custom configuration**: Per-tenant settings and feature flags
- **Security isolation**: Ensure tenants can only access their own data

## Quick Start

```php
use Synchrenity\Tenant\SynchrenityTenantManager;

// Initialize tenant manager
$tenantManager = new SynchrenityTenantManager();

// Register a tenant
$tenantManager->register('tenant-123', [
    'name' => 'Acme Corporation',
    'plan' => 'premium',
    'domain' => 'acme.example.com'
]);

// Set current tenant context
$tenantManager->setCurrentTenant('tenant-123');

// Get current tenant
$current = $tenantManager->getCurrentTenant();
echo $current['name']; // "Acme Corporation"
```

## Tenant Registration

### Basic Registration

```php
// Simple tenant registration
$tenantManager->register('tenant-001', [
    'name' => 'Company A',
    'email' => 'admin@company-a.com',
    'created_at' => date('Y-m-d H:i:s')
]);
```

### Advanced Registration with Configuration

```php
// Full tenant registration
$tenantManager->register(
    'tenant-002',
    // Metadata
    [
        'name' => 'Company B',
        'email' => 'admin@company-b.com',
        'plan' => 'enterprise',
        'domain' => 'company-b.myapp.com',
        'timezone' => 'America/New_York',
        'locale' => 'en_US'
    ],
    // Configuration
    [
        'features' => [
            'advanced_reports' => true,
            'api_access' => true,
            'custom_branding' => true
        ],
        'integrations' => [
            'slack' => ['webhook' => 'https://hooks.slack.com/...'],
            'email' => ['provider' => 'sendgrid', 'api_key' => 'sg.xxx']
        ],
        'security' => [
            'require_2fa' => true,
            'session_timeout' => 3600,
            'ip_whitelist' => ['192.168.1.0/24']
        ]
    ],
    // Quotas
    [
        'users' => 100,
        'storage_gb' => 50,
        'api_calls_per_hour' => 10000,
        'email_sends_per_month' => 50000
    ],
    // Billing
    [
        'plan' => 'enterprise',
        'monthly_fee' => 199.99,
        'per_user_fee' => 9.99,
        'billing_email' => 'billing@company-b.com',
        'payment_method' => 'stripe_customer_abc123'
    ]
);
```

## Tenant Context Management

### Setting Current Tenant

```php
// Set tenant by ID
$tenantManager->setCurrentTenant('tenant-123');

// Set tenant by domain (if configured)
$tenantManager->setCurrentTenantByDomain('acme.example.com');

// Set tenant by user ID (if user belongs to tenant)
$tenantManager->setCurrentTenantByUser($userId);
```

### Getting Tenant Information

```php
// Get current tenant
$current = $tenantManager->getCurrentTenant();

// Get specific tenant
$tenant = $tenantManager->getTenant('tenant-123');

// Get tenant configuration
$config = $tenantManager->getTenantConfig('tenant-123');

// Get tenant quotas
$quotas = $tenantManager->getTenantQuotas('tenant-123');
```

### Listing Tenants

```php
// List all tenants
$allTenants = $tenantManager->listTenants();

// List with filter
$premiumTenants = $tenantManager->listTenants(function($tenant, $id) {
    return $tenant['plan'] === 'premium';
});

// List active tenants
$activeTenants = $tenantManager->listActiveTenants();
```

## Configuration & Isolation

### Per-Tenant Configuration

```php
// Set tenant-specific config
$tenantManager->setTenantConfig('tenant-123', [
    'branding' => [
        'logo_url' => 'https://cdn.acme.com/logo.png',
        'primary_color' => '#FF6B35',
        'font_family' => 'Roboto'
    ],
    'features' => [
        'advanced_analytics' => true,
        'white_label' => true
    ]
]);

// Get tenant config value
$logoUrl = $tenantManager->getTenantConfigValue('tenant-123', 'branding.logo_url');

// Check feature flag
if ($tenantManager->hasFeature('tenant-123', 'advanced_analytics')) {
    // Show advanced analytics
}
```

### Database Isolation

```php
// Get tenant-specific database connection
$db = $tenantManager->getTenantDatabase('tenant-123');

// Get tenant schema/prefix
$schema = $tenantManager->getTenantSchema('tenant-123');
$prefix = $tenantManager->getTenantTablePrefix('tenant-123');

// Execute tenant-scoped query
$users = $tenantManager->query('tenant-123', 'SELECT * FROM users WHERE active = 1');
```

## Quotas & Billing

### Setting and Checking Quotas

```php
// Set quotas
$tenantManager->setTenantQuotas('tenant-123', [
    'users' => 50,
    'storage_mb' => 1000,
    'api_calls_per_day' => 5000
]);

// Check quota
if ($tenantManager->isWithinQuota('tenant-123', 'users', $currentUserCount)) {
    // Allow adding new user
} else {
    // Quota exceeded
}

// Get quota usage
$usage = $tenantManager->getQuotaUsage('tenant-123');
/*
[
    'users' => ['used' => 45, 'limit' => 50, 'percentage' => 90],
    'storage_mb' => ['used' => 750, 'limit' => 1000, 'percentage' => 75]
]
*/
```

### Usage Tracking

```php
// Track usage
$tenantManager->trackUsage('tenant-123', 'api_calls', 1);
$tenantManager->trackUsage('tenant-123', 'storage_mb', 0.5);

// Get usage for billing period
$usage = $tenantManager->getUsageForPeriod('tenant-123', '2024-01-01', '2024-01-31');

// Generate billing data
$billing = $tenantManager->generateBillingData('tenant-123', '2024-01');
```

## Security & Access Control

### Tenant Isolation

```php
// Ensure user can only access their tenant's data
$tenantManager->enforceTenantIsolation($userId, $tenantId);

// Validate tenant access
if (!$tenantManager->canUserAccessTenant($userId, $tenantId)) {
    throw new AccessDeniedException('User cannot access this tenant');
}
```

### Multi-Tenant Authentication

```php
// Authenticate user within tenant context
$user = $tenantManager->authenticateUser($email, $password, $tenantId);

// Switch user's active tenant
$tenantManager->switchUserTenant($userId, $newTenantId);

// Get user's accessible tenants
$userTenants = $tenantManager->getUserTenants($userId);
```

## Advanced Features

### Event Hooks

```php
// Register tenant lifecycle hooks
$tenantManager->onTenantCreate(function($tenantId, $metadata) {
    // Set up initial data
    // Send welcome email
    // Create default users
});

$tenantManager->onTenantUpdate(function($tenantId, $oldData, $newData) {
    // Log changes
    // Update integrations
});

$tenantManager->onTenantDelete(function($tenantId) {
    // Clean up resources
    // Archive data
    // Cancel subscriptions
});
```

### Tenant Middleware

```php
// Automatic tenant detection middleware
$tenantManager->addMiddleware(function($request, $next) {
    $domain = $request->getHost();
    $tenant = $this->getTenantByDomain($domain);
    
    if (!$tenant) {
        return new Response('Tenant not found', 404);
    }
    
    $this->setCurrentTenant($tenant['id']);
    return $next($request);
});
```

### Bulk Operations

```php
// Bulk tenant operations
$tenantManager->bulkUpdateConfig([
    'tenant-001' => ['feature.new_ui' => true],
    'tenant-002' => ['feature.new_ui' => true],
    'tenant-003' => ['feature.new_ui' => false]
]);

// Bulk quota updates
$tenantManager->bulkUpdateQuotas([
    'tenant-001' => ['users' => 100],
    'tenant-002' => ['users' => 150]
]);
```

### Tenant Analytics

```php
// Get tenant metrics
$metrics = $tenantManager->getTenantMetrics('tenant-123');
/*
[
    'total_users' => 45,
    'active_users_last_30_days' => 38,
    'api_calls_today' => 1250,
    'storage_used_mb' => 750,
    'revenue_this_month' => 199.99
]
*/

// Export tenant data
$export = $tenantManager->exportTenantData('tenant-123', [
    'include_users' => true,
    'include_data' => true,
    'format' => 'json'
]);
```

### Integration Examples

#### Database Service Integration

```php
// Custom database connection per tenant
$tenantManager->setDatabaseProvider(function($tenantId) {
    $config = $this->getTenantConfig($tenantId);
    return new PDO(
        $config['database']['dsn'],
        $config['database']['username'],
        $config['database']['password']
    );
});
```

#### Cache Service Integration

```php
// Tenant-scoped caching
$tenantManager->setCacheProvider(function($tenantId) {
    return new Redis([
        'prefix' => "tenant:{$tenantId}:",
        'database' => $this->getTenantConfig($tenantId)['cache']['database']
    ]);
});
```

#### File Storage Integration

```php
// Tenant-isolated file storage
$tenantManager->setStorageProvider(function($tenantId) {
    return new S3Storage([
        'bucket' => "app-tenant-{$tenantId}",
        'region' => $this->getTenantConfig($tenantId)['storage']['region']
    ]);
});
```

## Configuration Example

```php
$tenantManager = new SynchrenityTenantManager([
    'default_quotas' => [
        'users' => 10,
        'storage_mb' => 100,
        'api_calls_per_hour' => 1000
    ],
    'isolation_mode' => 'database', // 'database', 'schema', or 'prefix'
    'cache_enabled' => true,
    'audit_enabled' => true,
    'billing_enabled' => true,
    'auto_provisioning' => false
]);
```

## Best Practices

1. **Always validate tenant access** before performing operations
2. **Use tenant isolation** at the database level when possible
3. **Monitor quota usage** and implement alerts
4. **Audit tenant operations** for compliance
5. **Implement proper error handling** for tenant not found scenarios
6. **Use caching** for frequently accessed tenant data
7. **Plan for tenant data migration** and backup strategies
8. **Test multi-tenant scenarios** thoroughly

## Common Patterns

### Tenant-Aware Repository

```php
class UserRepository {
    private $tenantManager;
    
    public function findByEmail($email) {
        $tenantId = $this->tenantManager->getCurrentTenantId();
        return $this->db->query(
            'SELECT * FROM users WHERE email = ? AND tenant_id = ?',
            [$email, $tenantId]
        );
    }
}
```

### Tenant-Scoped Service

```php
class EmailService {
    public function send($to, $subject, $body) {
        $tenant = $this->tenantManager->getCurrentTenant();
        $config = $tenant['config']['email'];
        
        // Use tenant-specific email configuration
        return $this->mailer->send([
            'from' => $config['from_address'],
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'smtp' => $config['smtp']
        ]);
    }
}
```