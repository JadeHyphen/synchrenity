
# Synchrenity Authentication & Security

> Robust authentication, RBAC, OAuth2, SSO, and security best practices for modern PHP apps.

---

## 🔑 Authentication

```php
// Login
$core->auth->login($username, $password);
// Logout
$core->auth->logout();
// Register
$core->auth->register($userData);
// Check if logged in
$core->auth->check();
```

---

## 🛡️ OAuth2 & SSO

```php
$oauth2 = $core->oauth2Provider;
$authUrl = $oauth2->getAuthUrl('google', $state, true);
// Redirect user to $authUrl
$result = $oauth2->handleCallback('google', $_GET['code'], $_GET['state']);
if (isset($result['error'])) {
    // Handle error
} else {
    $token = $result['token'];
}
```

---

## 🏷️ RBAC (Roles & Permissions)

```php
// Check role
$core->auth->hasRole($userId, 'admin');
// Check permission
$core->auth->can($userId, 'edit_post');
```

---

## 🔒 Security Best Practices

- Use HTTPS and secure cookies
- Enable 2FA/MFA where possible
- Regularly rotate secrets and tokens
- Audit all auth events ([Audit Trail](AUDIT.md))
- Integrate with [Rate Limiter](RATELIMIT.md) for login endpoints

---

## 🧑‍💻 Example: Custom Auth Guard

```php
$core->auth->setGuard(function($user, $action) {
    // Custom logic
    return $user['active'] && $action !== 'delete_admin';
});
```

---

## 🔗 See Also

- [Audit Trail](AUDIT.md)
- [Rate Limiter](RATELIMIT.md)
- [Usage Guide](USAGE_GUIDE.md)
