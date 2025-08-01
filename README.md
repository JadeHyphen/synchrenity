# Synchrenity

[![Synchrenity CI](https://github.com/JadeHyphen/synchrenity/actions/workflows/ci.yml/badge.svg?branch=main&label=Synchrenity%20CI)](https://github.com/JadeHyphen/synchrenity/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Synchrenity is a scalable PHP framework for building enterprise-grade applications with advanced features, extensibility, and developer productivity.


# Synchrenity API Rate Limiting & OAuth2 Integration


## API Rate Limiting

Synchrenity provides a robust, dynamic API rate limiting system with:
- Centralized config (`config/api_rate_limits.php`) for per-endpoint and per-role limits
- Advanced limiter (`lib/RateLimit/SynchrenityRateLimiter.php`) with plugin/event support, burst, anomaly detection, and more
- Service container integration for easy access and dependency injection

**Example Usage (Service Container):**

```php
$rateLimiter = $synchrenityContainer->get('rate_limiter');
$apiLimits = $synchrenityContainer->get('api_rate_limits');
$limitConf = $apiLimits->get('GET:/api/resource', 'user');
$allowed = $rateLimiter->check('user123', 'GET:/api/resource');
if (!$allowed) {
    // Handle rate limit exceeded
}
```

## OAuth2 Provider

```php
// Redirect user to $authUrl
// On callback:
$result = $oauth2->handleCallback('google', $_GET['code'], $_GET['state']);
```
- [Enterprise Readiness](#enterprise-readiness)
- [Troubleshooting & FAQ](#troubleshooting--faq)
- [Release Info](#release-info)
- [Credits](#credits)

---

## Features
- Fast RESTful routing with middleware, priorities, rate limiting, and versioning
- Ultra-secure authentication, authorization, and encryption
- Advanced event system with hooks, audit logging, and async dispatch
- Powerful templating engine (Weave) with components, layouts, and filters
- Robust ORM (Atlas) for database abstraction and migrations
- Flexible pagination, error handling, and mailer
- CLI tools for migrations, seeds, and app management
- Enterprise integrations: SSO, monitoring, logging, cloud, compliance

---

## Quick Start
See [API Documentation](docs/API.md) and [Usage Guide](docs/USAGE_GUIDE.md) for more details.

### Option 1: Global Installation (Recommended)

Install the Synchrenity installer globally:
```sh
composer global require synchrenity/installer
```

Create a new application:
```sh
synchrenity new my-app
cd my-app
composer install
php synchrenity migrate
php synchrenity seed
```

### Option 2: Clone Repository

1. **Clone the repository:**
   ```sh
   git clone https://github.com/JadeHyphen/synchrenity.git
   cd synchrenity
2. **Install dependencies:**
   ```sh
   composer install
   ```
3. **Configure environment:**
   - Copy `.env.example` to `.env` and set your database and app keys
4. **Run migrations and seeders:**
   ```sh
   php synchrenity migrate
   php synchrenity seed
   ```
5. **Start the server:**
   ```sh
   ```

tests/              # Automated tests
docs/               # Documentation and guides
```

---

## Core Concepts
- **Routing:** Define routes with constraints, middleware, priorities, and versions
- **Middleware:** Global, route, and event middleware with async and error handling
- **Security:** Centralized manager for auth, encryption, validation, rate limiting, audit logging
- **Events:** Register listeners, hooks, and transactional events
- **Templating:** Use Weave for secure, extensible views and components
- **ORM:** Atlas for database models, migrations, and queries
- **Pagination:** SynchrenityPaginator for flexible, secure pagination
- **Error Handling:** Advanced error handler with reporting and custom responses
- **CLI:** Manage migrations, seeds, and app tasks from the command line

---

## Usage Examples

### Service Container & Facades

Synchrenity provides a robust service container for automatic dependency injection and global facades for easy access to core services.


**Registering Services:**
```php
$synchrenityContainer->register('auth', function($container) {
    return new \Synchrenity\Auth\SynchrenityAuth();
});
$synchrenityContainer->register('atlas', function($container) {
    $pdo = null; // Provide your PDO instance or DSN string
    return new \Synchrenity\Atlas\SynchrenityAtlas($pdo);
});
$synchrenityContainer->register('api_rate_limits', function($container) {
    return require __DIR__ . '/config/api_rate_limits.php';
});
$synchrenityContainer->register('rate_limiter', function($container) {
    $rateLimiter = new \Synchrenity\RateLimit\SynchrenityRateLimiter();
    $apiRateLimitsConfig = $container->get('api_rate_limits');
    $rateLimiter->setApiRateLimitsConfig($apiRateLimitsConfig);
    return $rateLimiter;
});
```


**Using Facades:**
```php
$user = Auth::user();
$table = Atlas::table('users');
```

**Using Rate Limiter and API Rate Limits:**
```php
$rateLimiter = $synchrenityContainer->get('rate_limiter');
$apiLimits = $synchrenityContainer->get('api_rate_limits');
$limitConf = $apiLimits->get('GET:/api/resource', 'user');
$allowed = $rateLimiter->check('user123', 'GET:/api/resource');
```

**Global Helpers:**
```php
auth()->impersonate($targetUserId);
atlas()->table('posts')->find($id);
```

### Logger Usage Example

SynchrenityLogger supports multi-channel logging and audit integration.

```php
### Audit Trail Integration

Inject the audit trail into core services for compliance and monitoring.

```php
$auth = synchrenity('auth');
$auditTrail = synchrenity('audit');
$auth->setAuditTrail($auditTrail);
```

### Advanced Service Container Features

- **Singletons:** `$container->singleton('service', fn($c) => new ServiceClass());`
- **Aliases:** `$container->alias('db', 'atlas');`
- **Contextual Bindings:** `$container->when(UserController::class, 'auth', fn($c) => new CustomAuth());`
- **Deferred Loading:** `$container->defer('heavyService', fn($c) => new HeavyService());`
- **Auto-wiring:** `$service = $container->make(ServiceClass::class);`

### Example: Creating a Custom Service Provider

```php
class MyServiceProvider {
    public function register($container) {
        $container->singleton('myService', function($c) {
            return new MyService();
        });
    }
}
$synchrenityContainer->registerProvider(new MyServiceProvider());
```
### Routing
```php
$router->add('GET', '/users', [UserController::class, 'index'], [AuthMiddleware::class], 'users_list');
```
### Middleware
```php
$middlewareManager->registerGlobal(function($payload, $context) {
    // Logging, rate limiting, etc.
});
```
### Security
```php
$securityManager->protectCSRF($token);
$securityManager->rateLimit($ip, 100, 60);
```
### Events
```php
$eventDispatcher->register('user.created', function($user) {
    // Send welcome email
});
```
### Templating
```php
echo $weave->render('users/list', ['users' => $users]);
```
### ORM
```php
$user = Atlas::table('users')->find($id);
```
### Pagination
### Error Handling
```php
try {
    // ...
} catch (Throwable $e) {
    $errorHandler->report($e);
}
```

### CLI

Synchrenity features a robust, colorized CLI for developer productivity:

- Colorized help and command output for clarity
- Command suggestion for mistyped commands
- Scaffolding commands: `make:controller`, `make:model`
- `optimize` command: runs Composer autoloader optimization and clears cache
- **`new` command: create new Synchrenity applications (like Laravel's `laravel new`)**

**Usage:**
```sh
php synchrenity help         # Show colorized help and available commands
php synchrenity new my-app   # Create a new Synchrenity application
php synchrenity make:controller UserController
php synchrenity make:model User
php synchrenity optimize    # Optimize autoloader and clear cache
```

**Global Usage:**
```sh
composer global require synchrenity/installer
synchrenity new my-app      # Create applications from anywhere
```

**Example colorized help output:**
```
Synchrenity CLI
Usage: php synchrenity <command> [args]

Available commands:
  make:controller   Scaffold a new controller
  make:model        Scaffold a new model
  optimize          Optimize the Synchrenity framework (autoloader, cache, etc)

Use 'php synchrenity help' for this message, or 'php synchrenity version' for version info.
```

### Application Bootstrap Example
```php
// In your application's bootstrap/app.php or public/index.php
require_once __DIR__ . '/../vendor/autoload.php';
$config = require_once __DIR__ . '/../config/app.php';
$core = new \Synchrenity\SynchrenityCore($config);
$core->handleRequest();
```

### Migration & Seeder Example
Create a migration in `database/migrations/`:
return [
    'up' => function($db) {
        $db->exec('CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255));');
        $db->exec('DROP TABLE users');
    }
];
```
Create a seeder in `database/seeders/`:
return function($db) {
    $db->exec("INSERT INTO users (name) VALUES ('Admin')");
};
---
## Testing
See [Test Suite Guide](tests/README.md) for more info.
- All tests are in the `tests/` folder
  ```sh
  php synchrenity test
  ```

---

## Deployment
See [Deployment Guide](docs/DEPLOYMENT.md) for Docker, cloud, and zero-downtime deployment.
- Integrate with CI/CD using `.github/workflows/ci.yml`

---

See [Enterprise Checklist](docs/ENTERPRISE_CHECKLIST.md) for a full list of requirements.
- Features: audit logging, compliance, monitoring, SSO, scaling, backups
See [Contributing Guide](docs/CONTRIBUTING.md) for details.
2. Add tests for new features
3. Submit a pull request with a detailed description
## License

---
- [Enterprise Checklist](docs/ENTERPRISE_CHECKLIST.md)
---

- [Discussions](https://github.com/JadeHyphen/synchrenity/discussions)

---
## Troubleshooting & FAQ
- **Composer install fails:** Check PHP version and extension requirements.
- **Migration errors:** Ensure your database is running and credentials are correct.
- **Permission issues:** Verify file and folder permissions for `storage/` and `database/`.
- **How do I add a new module?** See the Usage Guide and API docs for extension patterns.
- **Where do I report bugs?** Use GitHub Issues or Discord.

---

## Release Info
- Current version: `4.0.4`
- See [Releases](https://github.com/JadeHyphen/synchrenity/releases) for changelog and updates.

---

## Credits
- Synchrenity was founded by Jade Monathrae Lewis

## Authorization Policies

Synchrenity provides a robust and secure Policy system for fine-grained authorization:

**Register a Policy:**

```php
$container->get('policy')->register(User::class, UserPolicy::class);
```

**Define a Policy:**

```php
use Synchrenity\Security\Policy;

class UserPolicy extends Policy {
    public function update($user, $targetUser) {
        return $user->id === $targetUser->id;
    }
}
```

**Authorize an Action:**

```php
if ($container->get('policy')->authorize('update', User::class, $targetUser)) {
    // Allowed
} else {
    // Denied
}
```

You can override the `before` method in your policy to grant all abilities to certain users (e.g., super admin).
