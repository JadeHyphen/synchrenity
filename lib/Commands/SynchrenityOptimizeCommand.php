<?php

declare(strict_types=1);

namespace Synchrenity\Commands;

/**
 * SynchrenityOptimizeCommand: Checks for dependency errors, missing packages, and common bugs
 */
class SynchrenityOptimizeCommand extends \Synchrenity\SynchrenityCommand
{
    protected $name        = 'optimize';
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

        // Only check for dev packages if dev dependencies are installed
        $devInstalled = file_exists(__DIR__ . '/../../vendor/phpunit') || file_exists(__DIR__ . '/../../vendor/bin/phpunit');
        $missing      = [];
        $required     = [
            'phpunit/phpunit'           => 'PHPUnit\\Framework\\TestCase',
            'friendsofphp/php-cs-fixer' => 'PhpCsFixer\\Config',
            'phpstan/phpstan'           => 'PHPStan\\Command\\AnalyseCommand',
            'symfony/console'           => 'Symfony\\Component\\Console\\Application',
            'symfony/event-dispatcher'  => 'Symfony\\Component\\EventDispatcher\\EventDispatcher',
            'symfony/filesystem'        => 'Symfony\\Component\\Filesystem\\Filesystem',
            'symfony/finder'            => 'Symfony\\Component\\Finder\\Finder',
            'symfony/options-resolver'  => 'Symfony\\Component\\OptionsResolver\\OptionsResolver',
            'symfony/process'           => 'Symfony\\Component\\Process\\Process',
            'symfony/stopwatch'         => 'Symfony\\Component\\Stopwatch\\Stopwatch',
            'symfony/string'            => 'Symfony\\Component\\String\\UnicodeString',
        ];

        if ($devInstalled) {
            foreach ($required as $pkg => $class) {
                if (!class_exists($class)) {
                    $missing[] = $pkg;
                }
            }

            if ($missing) {
                $this->warn('Missing required packages: ' . implode(', ', $missing));
            } else {
                $this->info('All required packages are installed.');
            }
        } else {
            $this->info('Dev dependencies not installed; skipping dev package checks.');
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
