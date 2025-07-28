
<?php
// bootstrap/app.php

// Synchrenity Bootstrap File
// This file initializes the framework, loads configuration, and returns the core instance.

// Autoload dependencies using Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variable helper and .env support
require_once __DIR__ . '/../lib/Helpers/env.php';

// Load Synchrenity service container and facades automatically
require_once __DIR__ . '/../config/services.php';

// Load application configuration settings
$config = require_once __DIR__ . '/../config/app.php';

// Initialize the Synchrenity core framework
$core = new \Synchrenity\SynchrenityCore($config);

// Return the core instance to be used by the public entry point
return $core;
