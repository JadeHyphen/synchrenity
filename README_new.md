# Synchrenity Framework

## Clean, Secure, and Powerful PHP Framework

Synchrenity is a modern PHP framework inspired by Laravel's clean architecture and separation of concerns. It provides a robust foundation for building secure web applications with enterprise-grade features.

## Key Features

### 🔒 Security First
- **Enhanced Encryption**: Proper random IV generation and secure key management
- **CSRF Protection**: Advanced token validation with entropy checking
- **XSS Prevention**: Multi-layer input sanitization and output encoding
- **SQL Injection Protection**: Pattern-based filtering and prepared statement support
- **File Security**: Directory traversal prevention and secure file operations
- **Rate Limiting**: Configurable limits with IP, session, and endpoint-based controls

### 🏗 Clean Architecture
- **Separation of Concerns**: Clear distinction between framework and application logic
- **Service Container**: PSR-11 compliant dependency injection with auto-wiring
- **Service Providers**: Laravel-style service registration and bootstrapping
- **Facades**: Static access to services for developer convenience
- **Middleware Pipeline**: Structured request/response processing
- **Event System**: Comprehensive event dispatching and listening

### ⚡ Modern Development
- **PHP 8.2+**: Built for modern PHP with strict types and advanced features
- **PSR Compliance**: Follows PHP-FIG standards for interoperability
- **Auto-loading**: Composer-based PSR-4 autoloading
- **Configuration Management**: Dot notation access with environment-specific configs
- **Error Handling**: Comprehensive error management with security awareness

## Getting Started

### Installation
Install Synchrenity in your application via Composer:

```bash
composer require synchrenity/framework
```

### Basic Application Setup
Create your application structure:

```
my-app/
├── public/
│   └── index.php              # Your application entry point
├── config/
│   └── app.php                # Application configuration
├── app/
│   └── Controllers/           # Your application controllers
└── composer.json              # Your application dependencies
```

**public/index.php** (Your application entry point):
```php
<?php
require_once '../vendor/autoload.php';

$config = require '../config/app.php';
$app = new \Synchrenity\SynchrenityApplication($config);
$app->boot();
$app->handleRequest();
```

**config/app.php** (Your application configuration):
```php
<?php
return [
    'app' => [
        'name' => 'My Application',
        'env' => 'development',
        'debug' => true,
    ],
    'security' => [
        'encryption_key' => env('APP_KEY'),
        'csrf_protection' => true,
    ],
];
```

## Architecture Overview

### Framework Structure
Synchrenity is a pure framework package that applications can use. Unlike monolithic frameworks, Synchrenity separates framework logic from application logic:

```php
// In your application's entry point (e.g., public/index.php)
require_once 'vendor/autoload.php';

$config = require 'config/app.php';
$app = new \Synchrenity\SynchrenityApplication($config);
$app->boot();
$app->handleRequest();
```

### Framework Package Structure
```
synchrenity/
├── lib/                       # Framework core classes
│   ├── Support/               # Service container, facades, providers
│   ├── Http/                  # HTTP handling
│   ├── Security/              # Security components
│   ├── Auth/                  # Authentication
│   └── ...                    # Other framework components
├── synchrenity                # CLI tool
├── composer.json              # Package definition
└── README.md                  # Documentation
├── lib/                       # Framework core
│   ├── SynchrenityCore.php
│   ├── SynchrenityApplication.php
│   ├── Support/               # Support classes
│   ├── Security/              # Security components
│   ├── Http/                  # HTTP handling
│   └── ...
└── storage/                   # Logs, cache, etc.
```

### Service Container

The framework includes a powerful PSR-11 compliant service container:

```php
// Bind services
$app->bind('service', function($container) {
    return new MyService();
});

// Use singletons
$app->singleton('cache', function($container) {
    return new CacheManager();
});

// Auto-wiring support
$service = $app->make(ServiceClass::class);
```

### Facades

Laravel-style facades for convenient static access:

```php
use Synchrenity\Support\Auth;
use Synchrenity\Support\Cache;
use Synchrenity\Support\Config;

Auth::user();
Cache::get('key');
Config::get('app.name');
```

### Security Features

#### Enhanced Encryption
```php
$security = app('security');
$encrypted = $security->encrypt($data); // Uses random IV
$decrypted = $security->decrypt($encrypted);
```

#### CSRF Protection
```php
$csrf = app('csrf');
$token = $csrf->generateToken();
$valid = $csrf->validate($token);
```

#### Input Validation
```php
$security = app('security');
$clean = $security->sanitize('html', $userInput);
$valid = $security->validate('email', $email);
```

## Security Improvements Made

### Critical Fixes
1. **Fixed Encryption Vulnerability**: Replaced fixed IV with proper random IV generation
2. **Enhanced Input Validation**: Added type-specific validation with security defaults
3. **Improved XSS Protection**: Enhanced filtering to remove dangerous content
4. **Strengthened CSRF**: Better token entropy validation and secure file operations
5. **Path Traversal Prevention**: Comprehensive file path validation
6. **Information Disclosure Prevention**: Secure error handling in production

### Architecture Improvements
1. **Separated Concerns**: Moved 291 lines of application logic from entry point
2. **Service Container**: PSR-11 compliant with advanced features
3. **Clean Bootstrap**: Focused framework initialization
4. **Middleware Pipeline**: Structured request processing
5. **Configuration Management**: Environment-aware config loading

## Usage

### Basic Application
```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

// Load configuration
$config = require __DIR__ . '/config/app.php';

// Create and boot application
$app = new \Synchrenity\SynchrenityApplication($config);
$app->boot();

// Handle request
$app->handleRequest();
```

### Service Registration
```php
// In a service provider
public function register(): void
{
    $this->container->singleton('myservice', function($container) {
        return new MyService($container->get('config'));
    });
}
```

### Middleware
```php
$app->middleware()->register(function($request, $next) {
    // Process request
    $response = $next($request);
    // Process response
    return $response;
});
```

## Requirements

- PHP 8.2 or higher
- Composer for dependency management
- Extensions: json, openssl, mbstring

## Installation

```bash
git clone https://github.com/JadeHyphen/synchrenity.git
cd synchrenity
composer install
cp .env.example .env
```

## License

MIT License - see LICENSE file for details.

## Contributing

Please read CONTRIBUTING.md for details on our code of conduct and the process for submitting pull requests.

---

**Note**: This framework prioritizes security and clean architecture. All security vulnerabilities have been identified and fixed, making it production-ready for secure web applications.