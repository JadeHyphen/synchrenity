# Synchrenity Weave Templating Engine

Synchrenity Weave is an ultra-secure, extensible, and powerful templating engine with advanced features including sandboxed execution, custom directives, components, layouts, and more.

## Table of Contents

- [Quick Start](#quick-start)
- [Basic Usage](#basic-usage)
- [Template Syntax](#template-syntax)
- [Components](#components)
- [Layouts & Sections](#layouts--sections)
- [Filters](#filters)
- [Security Features](#security-features)
- [Advanced Features](#advanced-features)

## Quick Start

```php
use Synchrenity\Weave\SynchrenityWeave;

// Initialize Weave
$weave = new SynchrenityWeave('/path/to/templates', '/path/to/cache');

// Set variables
$weave->assign('user', ['name' => 'John', 'email' => 'john@example.com']);
$weave->assign('title', 'Welcome');

// Render template
echo $weave->render('welcome.weave.php');
```

## Basic Usage

### Setting Template Paths

```php
$weave = new SynchrenityWeave();
$weave->setTemplatePath('/app/views');
$weave->setCachePath('/app/cache/views');
```

### Variable Assignment

```php
// Single variable
$weave->assign('name', 'John Doe');

// Multiple variables
$weave->assignMultiple([
    'title' => 'Page Title',
    'users' => $userList,
    'config' => $appConfig
]);

// Global variables (available in all templates)
$weave->addGlobal('app_name', 'My Application');
$weave->addGlobal('version', '1.0.0');
```

## Template Syntax

### Variables and Expressions

```php
<!-- Simple variable output -->
{{ $name }}
{{ $user.name }}
{{ $user['email'] }}

<!-- With filters -->
{{ $name | upper }}
{{ $content | strip_tags | truncate:100 }}

<!-- Expressions -->
{{ $count + 1 }}
{{ $user.active ? 'Active' : 'Inactive' }}
```

### Control Structures

```php
<!-- If statements -->
@if($user.isAdmin)
    <p>Welcome, Administrator!</p>
@elseif($user.isActive)
    <p>Welcome, {{ $user.name }}!</p>
@else
    <p>Access denied</p>
@endif

<!-- Loops -->
@foreach($users as $user)
    <div>{{ $user.name }} - {{ $user.email }}</div>
@endforeach

@for($i = 0; $i < 10; $i++)
    <p>Item {{ $i }}</p>
@endfor

<!-- While loops -->
@while($condition)
    <!-- content -->
@endwhile
```

### Includes and Partials

```php
<!-- Include another template -->
@include('partials/header')
@include('partials/nav', ['active' => 'home'])

<!-- Include with data -->
@include('user-card', ['user' => $currentUser])
```

## Components

### Defining Components

```php
// Register a component
$weave->addComponent('alert', function($data, $slot) {
    $type = $data['type'] ?? 'info';
    $message = $data['message'] ?? $slot;
    return "<div class=\"alert alert-{$type}\">{$message}</div>";
});
```

### Using Components

```php
<!-- Simple component -->
@component('alert', ['type' => 'success', 'message' => 'Operation completed!'])

<!-- Component with slot content -->
@component('card', ['title' => 'User Profile'])
    <p>User information goes here...</p>
@endcomponent

<!-- Component with named slots -->
@component('modal')
    @slot('title')
        Confirm Action
    @endslot
    
    @slot('body')
        Are you sure you want to delete this item?
    @endslot
    
    @slot('footer')
        <button class="btn btn-danger">Delete</button>
        <button class="btn btn-secondary">Cancel</button>
    @endslot
@endcomponent
```

## Layouts & Sections

### Creating a Layout

```php
<!-- layouts/app.weave.php -->
<!DOCTYPE html>
<html>
<head>
    <title>@yield('title', 'Default Title')</title>
    @stack('styles')
</head>
<body>
    <header>
        @include('partials/nav')
    </header>
    
    <main>
        @yield('content')
    </main>
    
    <footer>
        @include('partials/footer')
    </footer>
    
    @stack('scripts')
</body>
</html>
```

### Extending Layouts

```php
<!-- pages/home.weave.php -->
@extends('layouts/app')

@section('title')
    Home - My Application
@endsection

@push('styles')
    <link rel="stylesheet" href="/css/home.css">
@endpush

@section('content')
    <h1>Welcome to the Homepage</h1>
    <p>This content goes into the main content area.</p>
@endsection

@push('scripts')
    <script src="/js/home.js"></script>
@endpush
```

## Filters

### Built-in Filters

```php
{{ $text | upper }}           <!-- Convert to uppercase -->
{{ $text | lower }}           <!-- Convert to lowercase -->
{{ $html | strip_tags }}      <!-- Remove HTML tags -->
{{ $text | truncate:50 }}     <!-- Truncate to 50 characters -->
{{ $date | date:'Y-m-d' }}     <!-- Format date -->
{{ $array | json }}           <!-- Convert to JSON -->
{{ $text | escape }}          <!-- HTML escape -->
{{ $number | number_format }} <!-- Format number -->
```

### Custom Filters

```php
// Register custom filter
$weave->addFilter('currency', function($value, $currency = 'USD') {
    return $currency . ' ' . number_format($value, 2);
});

// Usage in template
{{ $price | currency:'EUR' }}
```

## Security Features

### Automatic Escaping

Weave automatically escapes output to prevent XSS attacks:

```php
<!-- This is automatically escaped -->
{{ $userInput }}

<!-- Raw output (use with caution) -->
{!! $trustedHtml !!}
```

### Sandboxed Execution

Weave runs in secure mode by default, preventing access to dangerous functions:

```php
// Enable/disable secure mode
$weave->setSecureMode(true);

// Configure allowed functions
$weave->setAllowedFunctions(['strlen', 'substr', 'strtoupper']);

// Configure forbidden functions
$weave->setForbiddenFunctions(['exec', 'system', 'eval']);
```

### CSRF Protection

```php
<!-- Generate CSRF token -->
@csrf

<!-- Generate CSRF field -->
@csrf_field

<!-- Generate method field -->
@method('PUT')
```

## Advanced Features

### Caching

```php
// Enable template caching
$weave->setCacheEnabled(true);

// Cache fragments
@cache('user-list-' . $userId, 3600)
    <!-- Expensive operation here -->
    @foreach($users as $user)
        <!-- User rendering -->
    @endforeach
@endcache

// Clear cache
$weave->clearCache();
$weave->clearCache('specific-template');
```

### Macros

```php
// Define macro
$weave->addMacro('input', function($name, $value = '', $type = 'text') {
    return "<input type=\"{$type}\" name=\"{$name}\" value=\"{$value}\">";
});

// Use macro in template
@macro('input', 'username', $user.name)
@macro('input', 'password', '', 'password')
```

### Internationalization

```php
// Set locale
$weave->setLocale('en');

// Translation in templates
{{ __('welcome.message') }}
{{ __('user.greeting', ['name' => $user.name]) }}

// Pluralization
{{ trans_choice('message.items', $count) }}
```

### Error Handling

```php
// Custom error handler
$weave->setErrorHandler(function($error, $template, $line) {
    // Log error
    error_log("Weave error in {$template}:{$line} - {$error}");
    
    // Return fallback content
    return '<div class="template-error">Template error occurred</div>';
});

// Debug mode
$weave->setDebugMode(true);
```

### Asset Pipeline

```php
<!-- Asset helpers -->
@asset('css/app.css')
@asset('js/app.js')

<!-- With versioning -->
@asset('css/app.css', true)

<!-- Conditional assets -->
@if($environment === 'production')
    @asset('css/app.min.css')
@else
    @asset('css/app.css')
@endif
```

### Event Hooks

```php
// Before template render
$weave->onBeforeRender(function($template, $data) {
    // Modify data or template
    return [$template, $data];
});

// After template render
$weave->onAfterRender(function($output, $template) {
    // Modify output
    return $output;
});

// On error
$weave->onError(function($error, $template) {
    // Handle error
});
```

## Configuration Example

```php
$weave = new SynchrenityWeave([
    'template_path' => '/app/views',
    'cache_path' => '/app/cache/views',
    'cache_enabled' => true,
    'secure_mode' => true,
    'debug_mode' => false,
    'auto_escape' => true,
    'locale' => 'en',
    'charset' => 'UTF-8'
]);
```

## Best Practices

1. **Always use secure mode in production**
2. **Enable caching for better performance**
3. **Use components for reusable UI elements**
4. **Organize templates with layouts and partials**
5. **Escape user input unless you explicitly trust it**
6. **Use meaningful template names and directory structure**
7. **Test templates with different data scenarios**

## Performance Tips

- Enable template caching in production
- Use fragment caching for expensive operations
- Minimize deep nesting in templates
- Optimize included partials
- Use components for reusable elements
- Consider template compilation for high-traffic sites