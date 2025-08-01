<?php

declare(strict_types=1);

namespace Synchrenity\Installer;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class NewCommand extends Command
{
    protected static $defaultName = 'new';
    
    private Filesystem $filesystem;

    public function __construct()
    {
        parent::__construct();
        $this->filesystem = new Filesystem();
    }

    protected function configure(): void
    {
        $this->setDescription('Create a new Synchrenity application')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'The name of the new Synchrenity application'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite the directory if it already exists'
            )
            ->addOption(
                'dev',
                null,
                InputOption::VALUE_NONE,
                'Install the latest development version'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $name = $input->getArgument('name');
        $force = $input->getOption('force');
        $dev = $input->getOption('dev');

        // Validate project name
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
            $io->error('Project name must contain only letters, numbers, underscores, and hyphens.');
            return Command::FAILURE;
        }

        $directory = getcwd() . '/' . $name;

        // Check if directory exists
        if ($this->filesystem->exists($directory) && !$force) {
            $io->error("Directory '{$name}' already exists. Use --force to overwrite.");
            return Command::FAILURE;
        }

        $io->title("Creating Synchrenity application: {$name}");

        try {
            // Create directory
            if ($force && $this->filesystem->exists($directory)) {
                $io->comment('Removing existing directory...');
                $this->filesystem->remove($directory);
            }

            $this->filesystem->mkdir($directory);

            // Clone or download the framework
            $this->downloadFramework($directory, $dev, $io);

            // Setup the project
            $this->setupProject($directory, $name, $io);

            // Install dependencies
            $this->installDependencies($directory, $io);

            $io->success('Application created successfully!');
            
            $io->section('Next steps:');
            $io->text([
                "  cd {$name}",
                '  php synchrenity migrate',
                '  php synchrenity seed',
                '  php -S localhost:8000 -t public'
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Error creating application: ' . $e->getMessage());
            
            // Clean up on failure
            if ($this->filesystem->exists($directory)) {
                $this->filesystem->remove($directory);
            }
            
            return Command::FAILURE;
        }
    }

    private function downloadFramework(string $directory, bool $dev, SymfonyStyle $io): void
    {
        $io->section('Downloading Synchrenity framework...');

        // Use composer to create the project
        $branch = $dev ? 'dev-main' : '^1.0';
        
        // For now, we'll use git clone since the package might not be on packagist yet
        $gitUrl = 'https://github.com/JadeHyphen/synchrenity.git';
        
        $process = new Process(['git', 'clone', $gitUrl, $directory]);
        $process->setTimeout(300);
        
        $process->run(function ($type, $buffer) use ($io) {
            if (Process::ERR === $type) {
                $io->text('<comment>' . trim($buffer) . '</comment>');
            }
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Failed to download Synchrenity framework: ' . $process->getErrorOutput());
        }

        // Remove .git directory from the cloned project
        $this->filesystem->remove($directory . '/.git');
    }

    private function setupProject(string $directory, string $name, SymfonyStyle $io): void
    {
        $io->section('Setting up project...');

        // Update composer.json for the new project
        $composerPath = $directory . '/composer.json';
        if ($this->filesystem->exists($composerPath)) {
            $composerData = json_decode(file_get_contents($composerPath), true);
            
            // Update project-specific settings
            $composerData['name'] = strtolower($name) . '/app';
            $composerData['description'] = "A new Synchrenity application: {$name}";
            $composerData['type'] = 'project';
            
            // Remove the installer binary from project
            unset($composerData['bin']);
            
            file_put_contents(
                $composerPath,
                json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        }

        // Create .env file
        $this->createEnvFile($directory, $name);

        // Remove installer directory if it exists
        if ($this->filesystem->exists($directory . '/installer')) {
            $this->filesystem->remove($directory . '/installer');
        }

        // Remove installer composer.json if it exists
        if ($this->filesystem->exists($directory . '/installer-composer.json')) {
            $this->filesystem->remove($directory . '/installer-composer.json');
        }

        // Set proper permissions
        if ($this->filesystem->exists($directory . '/synchrenity')) {
            chmod($directory . '/synchrenity', 0755);
        }

        // Create storage directories
        $storageDirs = [
            $directory . '/storage/logs',
            $directory . '/storage/cache',
            $directory . '/storage/sessions',
        ];

        foreach ($storageDirs as $storageDir) {
            if (!$this->filesystem->exists($storageDir)) {
                $this->filesystem->mkdir($storageDir, 0755);
            }
        }
    }

    private function createEnvFile(string $directory, string $name): void
    {
        $appKey = 'base64:' . base64_encode(random_bytes(32));
        
        $envContent = <<<ENV
APP_NAME="{$name}"
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_KEY={$appKey}

DB_CONNECTION=sqlite
DB_DATABASE=database/app.db
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USERNAME=
DB_PASSWORD=

CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync

MAIL_MAILER=smtp
MAIL_HOST=localhost
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls

LOG_CHANNEL=daily
LOG_LEVEL=debug

FEATURE_RATE_LIMIT_METRICS=true
FEATURE_HOT_RELOAD=true
FEATURE_ADVANCED_AUDIT=true
ENV;

        file_put_contents($directory . '/.env', $envContent);
    }

    private function installDependencies(string $directory, SymfonyStyle $io): void
    {
        $io->section('Installing dependencies...');

        $process = new Process(['composer', 'install', '--no-dev', '--optimize-autoloader'], $directory);
        $process->setTimeout(300);
        
        $process->run(function ($type, $buffer) use ($io) {
            if (Process::OUT === $type) {
                $io->text('<info>' . trim($buffer) . '</info>');
            } else {
                $io->text('<comment>' . trim($buffer) . '</comment>');
            }
        });

        if (!$process->isSuccessful()) {
            $io->warning('Failed to install dependencies automatically. You can run "composer install" manually.');
        }
    }
}