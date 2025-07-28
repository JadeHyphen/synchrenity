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
        echo "\033[36mSynchrenity CLI\033[0m\n";
        echo "Usage: php synchrenity <command> [args]\n\n";
        echo "Available commands:\n";
        foreach ($this->commands as $name => $info) {
            echo "  \033[32m$name\033[0m\t" . ($info['description'] ?? '') . "\n";
        }
        echo "\nUse 'php synchrenity help' for this message, or 'php synchrenity version' for version info.\n";
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

        // Add more core commands as needed (migrations, modules, etc.)
    }
}
