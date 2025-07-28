<?php
namespace Synchrenity\Dev;

require_once __DIR__ . '/../Support/SynchrenityVersion.php';


class SynchrenityCli {
    protected $commands = [];

    public function register($name, callable $handler, $description = '') {
        $this->commands[$name] = [
            'handler' => $handler,
            'description' => $description
        ];
    }

    public function run($argv = null) {
        $argv = $argv ?: $_SERVER['argv'] ?? [];
        $script = array_shift($argv);
        $command = array_shift($argv);
        if (!$command) {
            $this->printHelp();
            exit(1);
        }
        if ($command === 'help') {
            $this->printHelp();
            exit(0);
        }
        if ($command === 'version') {
            $this->printVersion();
            exit(0);
        }
        if (!isset($this->commands[$command])) {
            $this->printError("Unknown command: $command");
            $suggestion = $this->suggestCommand($command);
            if ($suggestion) {
                echo "\033[33mDid you mean: $suggestion ?\033[0m\n";
            }
            $this->printHelp();
            exit(1);
        }
        $handler = $this->commands[$command]['handler'];
        return call_user_func($handler, $argv);
    }

    public function printHelp() {
        echo "\033[36;1mSynchrenity CLI\033[0m\n";
        echo "\033[37mUsage: php synchrenity \033[33m<command>\033[0m \033[35m[args]\033[0m\n\n";
        echo "\033[37;1mAvailable commands:\033[0m\n";
        foreach ($this->commands as $name => $info) {
            echo "  \033[32;1m$name\033[0m\t\033[90m" . ($info['description'] ?? '') . "\033[0m\n";
        }
        echo "\n\033[36mUse '\033[33mphp synchrenity help\033[36m' for this message, or '\033[33mphp synchrenity version\033[36m' for version info.\033[0m\n";
    }

    public function printError($msg) {
        echo "\033[31m$msg\033[0m\n";
    }

    public function printVersion() {
        $ver = defined('SYNCHRENITY_VERSION') ? SYNCHRENITY_VERSION : (json_decode(file_get_contents(__DIR__ . '/../../composer.json'))->version ?? 'dev');
        echo "Synchrenity Framework version: $ver\n";
    }

    public function suggestCommand($input) {
        $min = 999;
        $closest = null;
        foreach (array_keys($this->commands) as $cmd) {
            $lev = levenshtein($input, $cmd);
            if ($lev < $min) {
                $min = $lev;
                $closest = $cmd;
            }
        }
        return $min <= 3 ? $closest : null;
    }

    // Example: Register core scaffolding commands
    public function registerCoreCommands() {
        $this->register('make:controller', function($args) {
            $name = $args[0] ?? null;
            if (!$name) {
                echo "Controller name required.\n";
                return 1;
            }
            $file = __DIR__ . '/../Http/Controllers/' . $name . '.php';
            if (file_exists($file)) {
                echo "Controller already exists: $file\n";
                return 1;
            }
            $stub = "<?php\nnamespace Synchrenity\\Http\\Controllers;\n\nclass $name {\n    // ...\n}\n";
            file_put_contents($file, $stub);
            echo "Created controller: $file\n";
            return 0;
        }, 'Scaffold a new controller');

        $this->register('make:model', function($args) {
            $name = $args[0] ?? null;
            if (!$name) {
                echo "Model name required.\n";
                return 1;
            }
            $file = __DIR__ . '/../Models/' . $name . '.php';
            if (file_exists($file)) {
                echo "Model already exists: $file\n";
                return 1;
            }
            $stub = "<?php\nnamespace Synchrenity\\Models;\n\nclass $name {\n    // ...\n}\n";
            file_put_contents($file, $stub);
            echo "Created model: $file\n";
            return 0;
        }, 'Scaffold a new model');

        $this->register('optimize', function($args) {
            // Step 1: Composer autoloader optimization
            echo "\033[36;1m[Synchrenity]\033[0m \033[33mOptimizing Composer autoloader...\033[0m\n";
            $output = [];
            $result = 0;
            exec('composer dump-autoload -o 2>&1', $output, $result);
            foreach ($output as $line) {
                echo "  \033[90m$line\033[0m\n";
            }
            if ($result === 0) {
                echo "\033[32m[Synchrenity] Autoloader optimized successfully.\033[0m\n";
            } else {
                echo "\033[31m[Synchrenity] Autoloader optimization failed.\033[0m\n";
            }

            // Step 2: Clear cache (example: remove cache files/dirs)
            $cacheDir = __DIR__ . '/../../cache';
            if (is_dir($cacheDir)) {
                echo "\033[36;1m[Synchrenity]\033[0m \033[33mClearing cache...\033[0m\n";
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($cacheDir, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $fileinfo) {
                    $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                    $todo($fileinfo->getRealPath());
                }
                rmdir($cacheDir);
                echo "\033[32m[Synchrenity] Cache cleared.\033[0m\n";
            } else {
                echo "\033[33m[Synchrenity] No cache directory to clear.\033[0m\n";
            }

            // Step 3: (Placeholder) Config cache, route cache, etc.
            echo "\033[36;1m[Synchrenity]\033[0m \033[33mConfig and route cache not implemented yet.\033[0m\n";

            return $result;
        }, 'Optimize the Synchrenity framework (autoloader, cache, etc)');
        // Add more core commands as needed (migrations, modules, etc.)
    }
}
