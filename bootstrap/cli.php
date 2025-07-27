<?php
// bootstrap/cli.php

require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration if needed
$config = file_exists(__DIR__ . '/../config/app.php') ? require __DIR__ . '/../config/app.php' : [];

// Initialize CLI kernel
$kernel = new \Synchrenity\SynchrenityKernel();

// Register built-in and custom commands
$kernel->register(new \Synchrenity\Console\SynchrenityOptimizeCommand($kernel));
// Add other commands here as needed

// Run the CLI command
$args = $argv ?? [];
exit($kernel->handle($args));
