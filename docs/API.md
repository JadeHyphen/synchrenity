
# Synchrenity API Reference

> Comprehensive reference for all core classes, methods, extensibility points, and usage patterns.

---

## 📦 Core Classes

- `SynchrenityCore` — Application lifecycle, modules, events
- `SynchrenityRouter` — Routing, middleware, dispatch
- `SynchrenityPaginator` — Pagination utilities
- `SynchrenityWeave` — Event and plugin system
- `SynchrenityAtlas` — ORM and data models
- `SynchrenityAuth` — Authentication, RBAC, OAuth2
- `SynchrenityMailer` — Email sending and templates
- `SynchrenityErrorHandler` — Error and exception handling
- `SynchrenityEventDispatcher` — Event system
- `SynchrenityMiddlewareManager` — Middleware registration and chaining
- `SynchrenitySecurityManager` — Security utilities

---

## 🧑‍💻 Usage Examples

### Routing
```php
$core->router->get('/hello', fn() => 'Hello!');
```

### Middleware
```php
$core->middleware->add('auth', $authMiddleware);
```

### Security
```php
$core->security->csrfProtect($request);
```

### Pagination
```php
$results = $core->paginator->paginate($query, $page, $perPage);
```

### Templating
```php
echo $core->view->render('welcome', ['user' => $user]);
```

### ORM
```php
$user = \Synchrenity\Atlas\User::find($id);
```

### Auth
```php
$core->auth->login($username, $password);
```

### Mailer
```php
$core->mailer->send('user@example.com', 'Subject', 'template', ['data' => 'value']);
```

---

## 🔗 See Also

- [Usage Guide](USAGE_GUIDE.md)
- [Deployment Guide](DEPLOYMENT.md)
