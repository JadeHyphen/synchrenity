# Synchrenity Auth

## Overview
Provides authentication, authorization, and OAuth2 integration.

## Usage
```php
// Standard Auth
$core->auth->login($username, $password);
$core->auth->logout();
$core->auth->register($userData);
$core->auth->check();

// OAuth2
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

## RBAC
```php
$core->auth->hasRole($userId, 'admin');
$core->auth->can($userId, 'edit_post');
```
