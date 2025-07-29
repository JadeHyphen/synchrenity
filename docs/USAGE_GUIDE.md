
# Synchrenity Usage Guide

> Real-world examples, advanced usage, and extensibility patterns for Synchrenity.

---

## 🚀 Getting Started

### Installation
```bash
composer create-project synchrenity/synchrenity my-app
```

### Configuration
Edit `config/app.php`, `config/services.php`, etc.

### Directory Structure
- `app/` — Controllers, models, services
- `config/` — Configuration files
- `lib/` — Core framework modules
- `public/` — Web entrypoint
- `tests/` — Unit and integration tests

---

## 🛣️ Common Tasks

### Creating Routes
```php
$core->router->get('/hello', function() {
    return 'Hello, world!';
});
```

### Using Middleware
```php
$core->middleware->add('auth', function($request, $next) {
    if (!$core->auth->check()) {
        return $core->response->redirect('/login');
    }
    return $next($request);
});
```

### Securing Endpoints
See [Authentication](AUTH.md) and [Rate Limiter](RATELIMIT.md).

### Paginating Results
```php
$paginator = $core->paginator;
$results = $paginator->paginate($query, $page, $perPage);
```

### Rendering Views
```php
echo $core->view->render('welcome', ['user' => $user]);
```

### Running Migrations & Seeders
```bash
php bin/console migrate
php bin/console seed
```

---

## 🧑‍💻 Advanced Topics

### Event System
```php
$core->on('user.registered', function($user) {
    // Custom logic
});
```

### Custom Middleware
```php
$core->middleware->add('log', function($request, $next) {
    $core->audit()->log('request', ['uri' => $request->getUri()]);
    return $next($request);
});
```

### Extending the ORM
```php
class MyModel extends \Synchrenity\Atlas\Model {
    // Custom logic
}
```

### Integrating with External Services
```php
$core->notifier->send('user@example.com', 'Subject', 'template', ['data' => 'value']);
```

---

## 🧩 Extending the Framework

- Add new modules via the [Plugin System](PLUGIN.md)
- Register custom event listeners
- Hot-reload config and modules

---

## 🔗 See Also

- [API Reference](API.md)
- [Deployment Guide](DEPLOYMENT.md)
