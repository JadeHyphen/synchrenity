<?php
// Example of registering services in Synchrenity
use Synchrenity\Support\SynchrenityServiceContainer;
use Synchrenity\Auth\Auth;
use Synchrenity\ORM\Atlas;

// Global container instance
$synchrenityContainer = new SynchrenityServiceContainer();

// Register core services

$synchrenityContainer->register('auth', function($container) {
    return new \Synchrenity\Auth\SynchrenityAuth();
});
$synchrenityContainer->register('atlas', function($container) {
    // If you have a SynchrenityAtlas class, use it here. Otherwise, use Atlas facade directly.
    return new Atlas();
});
$synchrenityContainer->singleton('policy', function() {
    return new \Synchrenity\Security\PolicyManager();
});

// Set container for facades
Auth::setContainer($synchrenityContainer);
Atlas::setContainer($synchrenityContainer);

// Usage examples:
// $user = Auth::user();
// $table = Atlas::table('users');
