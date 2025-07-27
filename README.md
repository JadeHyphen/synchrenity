# Synchrenity Framework


[![Build Status](https://github.com/JadeHyphen/synchrenity/actions/workflows/ci.yml/badge.svg)](https://github.com/JadeHyphen/synchrenity/actions)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Synchrenity is a scalable PHP framework for building enterprise-grade applications with advanced features, extensibility, and developer productivity.

---

## Table of Contents
- [Features](#features)
- [Quick Start](#quick-start)
- [Directory Structure](#directory-structure)
- [Core Concepts](#core-concepts)
- [Usage Examples](#usage-examples)
- [Testing](#testing)
- [Deployment](#deployment)
- [Enterprise Readiness](#enterprise-readiness)
- [Contributing](#contributing)
- [License](#license)
- [Documentation](#documentation)
- [Community & Support](#community--support)
- [Troubleshooting & FAQ](#troubleshooting--faq)
- [Release Info](#release-info)
- [Credits](#credits)

---

## Features
- Fast RESTful routing with middleware, priorities, rate limiting, and versioning
- Ultra-secure authentication, authorization, and encryption
- Advanced event system with hooks, audit logging, and async dispatch
- Powerful templating engine (Forge) with components, layouts, and filters
- Robust ORM (Atlas) for database abstraction and migrations
- Flexible pagination, error handling, and mailer
- CLI tools for migrations, seeds, and app management
- Enterprise integrations: SSO, monitoring, logging, cloud, compliance

---

## Quick Start
See [API Documentation](docs/API.md) and [Usage Guide](docs/USAGE_GUIDE.md) for more details.

1. **Clone the repository:**
   ```sh
   git clone https://github.com/your-org/synchrenity.git
   cd synchrenity
   ```
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
   php -S localhost:8080 -t public
   ```

---

## Directory Structure
```
app/                # Application code (controllers, models, services)
config/             # Configuration files
lib/                # Framework core (Auth, Routing, Security, etc.)
database/           # Migrations, seeders, SQL scripts
public/             # Public web root (index.php)
tests/              # Automated tests
vendor/             # Composer dependencies
docs/               # Documentation and guides
```

---

## Core Concepts
- **Routing:** Define routes with constraints, middleware, priorities, and versions
- **Middleware:** Global, route, and event middleware with async and error handling
- **Security:** Centralized manager for auth, encryption, validation, rate limiting, audit logging
- **Events:** Register listeners, hooks, and transactional events
- **Templating:** Use Forge for secure, extensible views and components
- **ORM:** Atlas for database models, migrations, and queries
- **Pagination:** SynchrenityPaginator for flexible, secure pagination
- **Error Handling:** Advanced error handler with reporting and custom responses
- **CLI:** Manage migrations, seeds, and app tasks from the command line

---

## Usage Examples
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
echo $forge->render('users/list', ['users' => $users]);
```
### ORM
```php
$user = Atlas::table('users')->find($id);
```
### Pagination
```php
$paginator = new SynchrenityPaginator($data, $total, $page, $perPage);
echo ForgePagination::render($paginator);
```
### Error Handling
```php
try {
    // ...
} catch (Throwable $e) {
    $errorHandler->report($e);
}
```
### CLI
```sh
php synchrenity migrate
php synchrenity seed
```

### Migration & Seeder Example
Create a migration in `database/migrations/`:
```php
return [
    'up' => function($db) {
        $db->exec('CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255));');
    },
    'down' => function($db) {
        $db->exec('DROP TABLE users');
    }
];
```
Create a seeder in `database/seeders/`:
```php
return function($db) {
    $db->exec("INSERT INTO users (name) VALUES ('Admin')");
};
```

---

## Testing
See [Test Suite Guide](tests/README.md) for more info.
- All tests are in the `tests/` folder
- Run with PHPUnit or Synchrenity CLI:
  ```sh
  vendor/bin/phpunit --testdox
  php synchrenity test
  ```

---

## Deployment
See [Deployment Guide](docs/DEPLOYMENT.md) for Docker, cloud, and zero-downtime deployment.
- Use `docker-compose.yml` for local development
- Integrate with CI/CD using `.github/workflows/ci.yml`

---

## Enterprise Readiness
See [Enterprise Checklist](docs/ENTERPRISE_CHECKLIST.md) for a full list of requirements.
- Features: audit logging, compliance, monitoring, SSO, scaling, backups

---

## Contributing
See [Contributing Guide](docs/CONTRIBUTING.md) for details.
1. Fork the repo and create a feature branch
2. Add tests for new features
3. Submit a pull request with a detailed description

---

## License
MIT

---

## Documentation
- [API Documentation](docs/API.md)
- [Usage Guide](docs/USAGE_GUIDE.md)
- [Deployment Guide](docs/DEPLOYMENT.md)
- [Enterprise Checklist](docs/ENTERPRISE_CHECKLIST.md)

---

## Community & Support
- [Discord](https://discord.gg/your-synchrenity)
- [Discussions](https://github.com/your-org/synchrenity/discussions)
- [Issues](https://github.com/your-org/synchrenity/issues)

---

## Troubleshooting & FAQ
- **Composer install fails:** Check PHP version and extension requirements.
- **Migration errors:** Ensure your database is running and credentials are correct.
- **Permission issues:** Verify file and folder permissions for `storage/` and `database/`.
- **How do I add a new module?** See the Usage Guide and API docs for extension patterns.
- **Where do I report bugs?** Use GitHub Issues or Discord.

---

## Release Info
- Current version: `1.0.0`
- See [Releases](https://github.com/your-org/synchrenity/releases) for changelog and updates.

---

## Credits
- Synchrenity was founded by Jade Monathrae Lewis
- Synchrenity core team
- Contributors: see [GitHub Contributors](https://github.com/your-org/synchrenity/graphs/contributors)
- Special thanks to the PHP and open source community
