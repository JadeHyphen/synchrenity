<?php
// bootstrap/cli.php


require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/SynchrenityVersionManager.php';
use Synchrenity\Bootstrap\SynchrenityVersionManager;

// Load configuration if needed

$argv = $_SERVER['argv'] ?? [];
if (isset($argv[1]) && $argv[1] === 'version') {
    $manager = new SynchrenityVersionManager();
    if (isset($argv[2]) && $argv[2] === 'bump') {
        $type = $argv[3] ?? 'patch';
        $newVersion = $manager->bump($type);
        echo "Version bumped to $newVersion\n";
        // Git tag
        $tag = "v$newVersion";
        exec("git add composer.json");
        exec("git commit -m 'Bump version to $newVersion'");
        exec("git tag $tag");
        exec("git push && git push --tags");
        exit(0);
    } else {
        echo "Current version: " . $manager->getVersion() . "\n";
        exit(0);
    }
}

$config = file_exists(__DIR__ . '/../config/app.php') ? require __DIR__ . '/../config/app.php' : [];

// Initialize CLI kernel (auto-discovers all commands)

// Fallback: Use SynchrenityCli if SynchrenityKernel is not available
if (class_exists('Synchrenity\\Dev\\SynchrenityCli')) {
    $cli = new \Synchrenity\Dev\SynchrenityCli();
    $cli->registerCoreCommands();
    $cli->run($argv);
    exit(0);
} else {
    echo "No CLI kernel or SynchrenityCli found.\n";
    exit(1);
}
