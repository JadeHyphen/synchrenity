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

        // Create Laravel-like directory structure for user code
        $appDirs = [
            'app/Controllers',
            'app/Models',
            'app/Middleware',
            'app/Services',
            'public',
            'resources/views',
            'resources/assets/css',
            'resources/assets/js',
            'resources/assets/images',
            'routes',
            'storage/logs',
            'storage/cache',
            'storage/sessions',
            'storage/uploads',
            'storage/framework/views'
        ];

        foreach ($appDirs as $dir) {
            $dirPath = $targetDir . '/' . $dir;
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0755, true);
            }
        }

        // Create scaffold files for Laravel-like structure
        $this->createPublicIndex($targetDir);
        $this->createRouteFiles($targetDir);
        $this->createResourceFiles($targetDir);
        $this->createExampleController($targetDir);
        
        // Copy assets from resources to public after creating them
        $this->setupPublicAssets($targetDir);
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

// Check if this is a request for static assets
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$requestPath = parse_url($requestUri, PHP_URL_PATH);

// Serve static assets directly
if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$/i', $requestPath)) {
    $assetPath = __DIR__ . '/assets' . $requestPath;
    if (file_exists($assetPath)) {
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject'
        ];
        
        $extension = strtolower(pathinfo($assetPath, PATHINFO_EXTENSION));
        $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
        
        header('Content-Type: ' . $mimeType);
        header('Cache-Control: public, max-age=31536000'); // 1 year cache
        readfile($assetPath);
        exit;
    }
}

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

    # Serve static assets directly
    RewriteCond %{REQUEST_FILENAME} -f
    RewriteCond %{REQUEST_URI} \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ [NC]
    RewriteRule ^ - [L]

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

    /**
     * Setup public assets (copy from resources to public/assets)
     */
    private function setupPublicAssets(string $targetDir): void
    {
        $publicAssetsDir = $targetDir . '/public/assets';
        $resourcesAssetsDir = $targetDir . '/resources/assets';
        
        if (!is_dir($publicAssetsDir)) {
            mkdir($publicAssetsDir, 0755, true);
        }
        
        // Copy CSS files
        if (is_dir($resourcesAssetsDir . '/css')) {
            $publicCssDir = $publicAssetsDir . '/css';
            if (!is_dir($publicCssDir)) {
                mkdir($publicCssDir, 0755, true);
            }
            $this->copyDirectory($resourcesAssetsDir . '/css', $publicCssDir);
        }
        
        // Copy JS files
        if (is_dir($resourcesAssetsDir . '/js')) {
            $publicJsDir = $publicAssetsDir . '/js';
            if (!is_dir($publicJsDir)) {
                mkdir($publicJsDir, 0755, true);
            }
            $this->copyDirectory($resourcesAssetsDir . '/js', $publicJsDir);
        }
        
        // Copy image files
        if (is_dir($resourcesAssetsDir . '/images')) {
            $publicImagesDir = $publicAssetsDir . '/images';
            if (!is_dir($publicImagesDir)) {
                mkdir($publicImagesDir, 0755, true);
            }
            $this->copyDirectory($resourcesAssetsDir . '/images', $publicImagesDir);
        }
    }

    /**
     * Create basic route files
     */
    private function createRouteFiles(string $targetDir): void
    {
        // Create web.php routes file
        $webRoutesContent = <<<'PHP'
<?php

/**
 * Web Routes
 * 
 * Here is where you can register web routes for your application.
 */

use Synchrenity\Routing\Router;

// Basic welcome route
Router::get('/', function() {
    return view('welcome');
});

// Example routes
Router::get('/home', 'HomeController@index');
Router::get('/about', function() {
    return view('about');
});

PHP;

        file_put_contents($targetDir . '/routes/web.php', $webRoutesContent);

        // Create api.php routes file  
        $apiRoutesContent = <<<'PHP'
<?php

/**
 * API Routes
 * 
 * Here is where you can register API routes for your application.
 */

use Synchrenity\Routing\Router;

// API routes with prefix
Router::prefix('api')->group(function() {
    Router::get('/', function() {
        return json(['message' => 'API is working', 'version' => '1.0']);
    });
    
    Router::get('/status', function() {
        return json(['status' => 'healthy', 'timestamp' => time()]);
    });
});

PHP;

        file_put_contents($targetDir . '/routes/api.php', $apiRoutesContent);
    }

    /**
     * Create basic resource files and templates
     */
    private function createResourceFiles(string $targetDir): void
    {
        // Create welcome view template
        $welcomeTemplate = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Synchrenity</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Welcome to Synchrenity</h1>
            <p>Your application is ready!</p>
        </header>
        
        <main>
            <section class="welcome-section">
                <h2>Getting Started</h2>
                <p>You've successfully created a new Synchrenity application.</p>
                
                <div class="next-steps">
                    <h3>Next Steps:</h3>
                    <ul>
                        <li>Configure your database in <code>.env</code></li>
                        <li>Run migrations: <code>php synchrenity migrate</code></li>
                        <li>Create your first controller: <code>php synchrenity make:controller</code></li>
                        <li>Edit routes in <code>routes/web.php</code></li>
                    </ul>
                </div>
            </section>
        </main>
        
        <footer>
            <p>Built with ❤️ using Synchrenity Framework</p>
        </footer>
    </div>
    
    <script src="/assets/js/app.js"></script>
</body>
</html>
HTML;

        file_put_contents($targetDir . '/resources/views/welcome.weave', $welcomeTemplate);

        // Create about view template
        $aboutTemplate = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About - Synchrenity</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>About This Application</h1>
        </header>
        
        <main>
            <section>
                <p>This is a Synchrenity application.</p>
                <p><a href="/">← Back to Home</a></p>
            </section>
        </main>
    </div>
    
    <script src="/assets/js/app.js"></script>
</body>
</html>
HTML;

        file_put_contents($targetDir . '/resources/views/about.weave', $aboutTemplate);

        // Create basic CSS file
        $cssContent = <<<'CSS'
/* Synchrenity Application Styles */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    line-height: 1.6;
    color: #333;
    background-color: #f8f9fa;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}

header {
    text-align: center;
    margin-bottom: 3rem;
}

header h1 {
    font-size: 3rem;
    color: #2c3e50;
    margin-bottom: 0.5rem;
}

header p {
    font-size: 1.2rem;
    color: #6c757d;
}

.welcome-section {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.welcome-section h2 {
    color: #2c3e50;
    margin-bottom: 1rem;
}

.next-steps {
    margin-top: 2rem;
    padding: 1.5rem;
    background-color: #e8f5e8;
    border-radius: 6px;
    border-left: 4px solid #28a745;
}

.next-steps h3 {
    color: #155724;
    margin-bottom: 1rem;
}

.next-steps ul {
    list-style-type: none;
    padding-left: 0;
}

.next-steps li {
    margin-bottom: 0.5rem;
    padding-left: 1.5rem;
    position: relative;
}

.next-steps li:before {
    content: "✓";
    position: absolute;
    left: 0;
    color: #28a745;
    font-weight: bold;
}

code {
    background-color: #f8f9fa;
    padding: 0.2rem 0.4rem;
    border-radius: 3px;
    font-family: 'Monaco', 'Consolas', monospace;
    font-size: 0.9rem;
    color: #e83e8c;
}

footer {
    text-align: center;
    margin-top: 3rem;
    padding-top: 2rem;
    border-top: 1px solid #dee2e6;
    color: #6c757d;
}

/* Responsive design */
@media (max-width: 768px) {
    .container {
        padding: 1rem;
    }
    
    header h1 {
        font-size: 2rem;
    }
    
    .welcome-section {
        padding: 1.5rem;
    }
}
CSS;

        file_put_contents($targetDir . '/resources/assets/css/app.css', $cssContent);

        // Create basic JavaScript file
        $jsContent = <<<'JS'
// Synchrenity Application JavaScript

document.addEventListener('DOMContentLoaded', function() {
    console.log('Synchrenity application loaded!');
    
    // Add any global JavaScript functionality here
    
    // Example: Simple welcome animation
    const header = document.querySelector('header h1');
    if (header) {
        header.style.opacity = '0';
        header.style.transform = 'translateY(-20px)';
        header.style.transition = 'all 0.6s ease';
        
        setTimeout(() => {
            header.style.opacity = '1';
            header.style.transform = 'translateY(0)';
        }, 100);
    }
});

// Example utility function
function showNotification(message, type = 'info') {
    // Simple notification system - you can enhance this
    const notification = document.createElement('div');
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        background: ${type === 'error' ? '#dc3545' : '#28a745'};
        color: white;
        border-radius: 4px;
        z-index: 1000;
        transition: all 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// Make utility functions available globally
window.SynchrenityApp = {
    showNotification
};
JS;

        file_put_contents($targetDir . '/resources/assets/js/app.js', $jsContent);

        // Create a basic image placeholder (1x1 transparent GIF)
        $placeholderImage = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        file_put_contents($targetDir . '/resources/assets/images/.gitkeep', '# Images directory');
    }

    /**
     * Create an example HomeController
     */
    private function createExampleController(string $targetDir): void
    {
        $controllerContent = <<<'PHP'
<?php

namespace App\Controllers;

/**
 * Home Controller
 * 
 * Example controller for handling home page requests
 */
class HomeController
{
    /**
     * Display the home page
     */
    public function index()
    {
        return view('welcome', [
            'title' => 'Welcome to Synchrenity',
            'message' => 'Your application is running successfully!'
        ]);
    }

    /**
     * Display the about page
     */
    public function about()
    {
        return view('about', [
            'title' => 'About This Application'
        ]);
    }
}
PHP;

        file_put_contents($targetDir . '/app/Controllers/HomeController.php', $controllerContent);

        // Create a basic README for the app directory
        $appReadmeContent = <<<'MD'
# Application Directory

This directory contains your application's business logic.

## Structure

- **Controllers/** - Handle HTTP requests and return responses
- **Models/** - Represent your data and business logic
- **Middleware/** - Filter HTTP requests entering your application
- **Services/** - Contain reusable business logic

## Getting Started

1. Create controllers: `php synchrenity make:controller UserController`
2. Create models: `php synchrenity make:model User`
3. Define routes in `routes/web.php` or `routes/api.php`
4. Create views in `resources/views/`

Happy coding!
MD;

        file_put_contents($targetDir . '/app/README.md', $appReadmeContent);
    }
}