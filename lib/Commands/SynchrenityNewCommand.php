<?php

declare(strict_types=1);

namespace Synchrenity\Commands;

use Synchrenity\SynchrenityCommand;

class SynchrenityNewCommand extends SynchrenityCommand
{
    protected string $name        = 'new';
    protected string $description = 'Create a new Synchrenity application.';
    protected string $usage       = 'new <project-name> [--force]';

    public function __construct($kernel = null)
    {
        parent::__construct($kernel);
        $this->addArgument('project-name', 'Name of the new project', true);
        $this->addFlag('force', 'Overwrite existing directory if it exists');
    }

    public function handle(array $args, array $options, array $flags)
    {
        $projectName = $args[0] ?? null;

        if (!$projectName) {
            $this->error('Project name is required.');
            $this->line('Usage: synchrenity new <project-name>');
            return 1;
        }

        // Validate project name
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $projectName)) {
            $this->error('Project name must contain only letters, numbers, underscores, and hyphens.');
            return 1;
        }

        $force = isset($flags['force']);
        $targetDir = getcwd() . '/' . $projectName;

        // Check if directory exists
        if (is_dir($targetDir) && !$force) {
            $this->error("Directory '$projectName' already exists. Use --force to overwrite.");
            return 1;
        }

        $this->info("Creating new Synchrenity application: $projectName");

        try {
            // Create project directory
            if (!is_dir($targetDir)) {
                if (!mkdir($targetDir, 0755, true)) {
                    $this->error("Failed to create directory: $targetDir");
                    return 1;
                }
            }

            // Get the template directory (current framework location)
            $templateDir = realpath(__DIR__ . '/../../');
            if (!$templateDir) {
                $this->error('Could not find Synchrenity template directory.');
                return 1;
            }

            // Copy framework files
            $this->info('Copying framework files...');
            $this->copyProjectFiles($templateDir, $targetDir);

            // Initialize composer.json for the new project
            $this->info('Initializing composer.json...');
            $this->createComposerJson($targetDir, $projectName);

            // Create .env file
            $this->info('Creating environment configuration...');
            $this->createEnvFile($targetDir, $projectName);

            // Set executable permissions on synchrenity file
            if (file_exists($targetDir . '/synchrenity')) {
                chmod($targetDir . '/synchrenity', 0755);
            }

            $this->info('');
            $this->info("✅ Application created successfully!");
            $this->info('');
            $this->info("Next steps:");
            $this->info("  cd $projectName");
            $this->info("  composer install");
            $this->info("  php synchrenity migrate");
            $this->info("  php synchrenity seed");
            $this->info('');

            return 0;

        } catch (\Exception $e) {
            $this->error('Error creating application: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Copy project files from template to target directory
     */
    private function copyProjectFiles(string $templateDir, string $targetDir): void
    {
        // Files and directories to include in new project
        $includePatterns = [
            'bootstrap',
            'config',
            'database',
            'lib',
            'tests',
            'docs',
            'synchrenity',
            '.env.example',
            '.gitignore',
            '.php-cs-fixer.dist.php',
            'phpunit.xml',
            'Dockerfile',
            'docker-compose.yml',
            'README.md',
            'LICENSE'
        ];

        // Files and directories to exclude
        $excludePatterns = [
            '.git',
            '.github',
            'vendor',
            'node_modules',
            '.DS_Store',
            '.env',
            'composer.lock',
            '.phpunit.result.cache',
            'installer'
        ];

        foreach ($includePatterns as $pattern) {
            $sourcePath = $templateDir . '/' . $pattern;
            $targetPath = $targetDir . '/' . $pattern;

            if (is_file($sourcePath)) {
                $this->copyFile($sourcePath, $targetPath);
            } elseif (is_dir($sourcePath)) {
                $this->copyDirectory($sourcePath, $targetPath, $excludePatterns);
            }
        }

        // Create app directory structure for user code
        $appDirs = [
            'app/Controllers',
            'app/Models',
            'app/Middleware',
            'app/Services',
            'public',
            'storage/logs',
            'storage/cache',
            'storage/sessions'
        ];

        foreach ($appDirs as $dir) {
            $dirPath = $targetDir . '/' . $dir;
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0755, true);
            }
        }

        // Create a basic index.php for the public directory
        $this->createPublicIndex($targetDir);
    }

    /**
     * Copy a single file
     */
    private function copyFile(string $source, string $target): void
    {
        $targetDir = dirname($target);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        copy($source, $target);
    }

    /**
     * Copy a directory recursively
     */
    private function copyDirectory(string $source, string $target, array $excludePatterns = []): void
    {
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($source) + 1);
            
            // Skip excluded patterns
            $skip = false;
            foreach ($excludePatterns as $pattern) {
                if (strpos($relativePath, $pattern) !== false) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            $targetPath = $target . '/' . $relativePath;

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                $this->copyFile($item->getPathname(), $targetPath);
            }
        }
    }

    /**
     * Create composer.json for the new project
     */
    private function createComposerJson(string $targetDir, string $projectName): void
    {
        $composerData = [
            'name' => strtolower($projectName) . '/app',
            'description' => "A new Synchrenity application",
            'type' => 'project',
            'license' => 'MIT',
            'require' => [
                'php' => '>=8.2',
                'synchrenity/framework' => '*'
            ],
            'require-dev' => [
                'friendsofphp/php-cs-fixer' => '^3.85',
                'phpstan/phpstan' => '^2.1',
                'phpunit/phpunit' => '^10.5'
            ],
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'app/',
                    'Database\\' => 'database/'
                ]
            ],
            'autoload-dev' => [
                'psr-4' => [
                    'Tests\\' => 'tests/'
                ]
            ],
            'scripts' => [
                'test' => 'phpunit',
                'cs-fix' => 'php-cs-fixer fix'
            ],
            'config' => [
                'optimize-autoloader' => true,
                'sort-packages' => true,
                'preferred-install' => 'dist'
            ],
            'minimum-stability' => 'stable',
            'prefer-stable' => true
        ];

        file_put_contents(
            $targetDir . '/composer.json', 
            json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Create .env file for the new project
     */
    private function createEnvFile(string $targetDir, string $projectName): void
    {
        $appKey = $this->generateAppKey();
        
        $envContent = <<<ENV
APP_NAME="$projectName"
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_KEY=$appKey

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

        file_put_contents($targetDir . '/.env', $envContent);
    }

    /**
     * Generate a random application key
     */
    private function generateAppKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    /**
     * Create a basic public/index.php file
     */
    private function createPublicIndex(string $targetDir): void
    {
        $indexContent = <<<'PHP'
<?php

/**
 * Synchrenity Application Entry Point
 */

// Define the start time for the application
define('SYNCHRENITY_START', microtime(true));

// Load the bootstrap file
$app = require_once __DIR__ . '/../bootstrap/app.php';

// Handle the incoming request
$app->handleRequest();
PHP;

        file_put_contents($targetDir . '/public/index.php', $indexContent);

        // Create a basic .htaccess for Apache
        $htaccessContent = <<<'HTACCESS'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
HTACCESS;

        file_put_contents($targetDir . '/public/.htaccess', $htaccessContent);
    }
}