<?php
namespace Synchrenity\Console;

use Synchrenity\SynchrenityCore;

/**
 * SynchrenityOptimizeCommand: Checks for dependency errors, missing packages, and common bugs
 */
class SynchrenityOptimizeCommand extends \Synchrenity\SynchrenityCommand
{
    protected $name = 'optimize';
    protected $description = 'Check for dependency errors, missing packages, and common bugs.';

    public function handle(array $args, array $options, array $flags)
    {
        $this->info('Running Synchrenity optimization checks...');

        // Check Composer dependencies
        if (!file_exists(__DIR__ . '/../../vendor/autoload.php')) {
            $this->error('Composer autoload not found. Run "composer install".');
            return 1;
        }
        $this->info('Composer autoload found.');

        // Check for missing required packages
        $missing = [];
        $required = [
            'phpunit/phpunit',
            'symfony/console',
            'symfony/event-dispatcher',
            'symfony/filesystem',
            'symfony/finder',
            'symfony/options-resolver',
            'symfony/process',
            'symfony/stopwatch',
            'symfony/string'
        ];
        foreach ($required as $pkg) {
            if (!class_exists(str_replace('/', '\\', $pkg))) {
                $missing[] = $pkg;
            }
        }
        if ($missing) {
            $this->warn('Missing required packages: ' . implode(', ', $missing));
        } else {
            $this->info('All required packages are installed.');
        }

        // Check for common bugs (autoload, config, env)
        if (!file_exists(__DIR__ . '/../../config/app.php')) {
            $this->warn('Missing config/app.php.');
        } else {
            $this->info('Config file found.');
        }
        if (!file_exists(__DIR__ . '/../../.env')) {
            $this->warn('Missing .env file.');
        } else {
            $this->info('.env file found.');
        }

        $this->info('Optimization checks complete.');
        return 0;
    }
}
