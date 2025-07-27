<?php
// bootstrap/cli.php


require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration if needed
$config = file_exists(__DIR__ . '/../config/app.php') ? require __DIR__ . '/../config/app.php' : [];

// Initialize CLI kernel (auto-discovers all commands)
$kernel = new \Synchrenity\SynchrenityKernel();

// Run the CLI command
$args = array_slice($argv ?? [], 1);
exit($kernel->handle($args));
