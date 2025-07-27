# Synchrenity Plugin Manager

## Overview
Plugin registration, activation, hooks, and audit logging.

## Usage
```php
$plugin = $core->plugin;
$plugin->register('MyPlugin', $pluginInstance);
$plugin->activate('MyPlugin');
$plugin->on('plugin.activated', function($meta) {
    // Custom logic
});
```
