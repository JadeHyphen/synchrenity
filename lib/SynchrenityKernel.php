<?php
namespace Synchrenity;

// SynchrenityKernel: Robust CLI kernel for Synchrenity
class SynchrenityKernel
{
    /**
     * @var SynchrenityCommand[] Registered command instances
     */
    protected $commands = [];

    public function __construct()
    {
        // Auto-discover commands in lib/Commands/
        $this->discoverCommands(__DIR__ . '/Commands');
        // Always register built-in commands
        $this->register(new \Synchrenity\Commands\SynchrenityListCommand($this));
        $this->register(new \Synchrenity\Commands\SynchrenityHelpCommand($this));
    }

    /**
     * Discover and register all SynchrenityCommand classes in a directory
     */
    protected function discoverCommands($dir)
    {
        if (!is_dir($dir)) return;
        foreach (glob($dir . '/*.php') as $file) {
            $before = get_declared_classes();
            require_once $file;
            $after = get_declared_classes();
            $newClasses = array_diff($after, $before);
            foreach ($newClasses as $class) {
                if (is_subclass_of($class, '\Synchrenity\SynchrenityCommand') && $class !== 'SynchrenityCommand') {
                    $this->register(new $class($this));
                }
            }
        }
    }

    public function register(SynchrenityCommand $command)
    {
        $this->commands[$command->getName()] = $command;
    }

    public function getCommand($name)
    {
        return $this->commands[$name] ?? null;
    }

    public function getCommandNames()
    {
        return array_keys($this->commands);
    }

    /**
     * Advanced CLI argument and option parsing, error handling, and execution
     */
    public function handle(array $args)
    {
        if (empty($args)) {
            $args = ['list'];
        }
        $commandName = $args[0];
        $commandName = preg_replace('/[^a-zA-Z0-9:_-]/', '', $commandName);
        $command = $this->getCommand($commandName);
        if ($command) {
            // Parse options and flags
            $parsed = $this->parseArgs(array_slice($args, 1));
            try {
                return $command->handle($parsed['args'], $parsed['options'], $parsed['flags']);
            } catch (\Throwable $e) {
                self::errorBlock($e->getMessage());
                return 1;
            }
        }
        self::errorBlock("Unknown command: $commandName");
        return 1;
    }

    /**
     * Parse CLI arguments into args, options, and flags
     */
    protected function parseArgs(array $input)
    {
        $args = [];
        $options = [];
        $flags = [];
        foreach ($input as $item) {
            if (preg_match('/^--([a-zA-Z0-9_-]+)=(.*)$/', $item, $m)) {
                $options[$m[1]] = $m[2];
            } elseif (preg_match('/^--([a-zA-Z0-9_-]+)$/', $item, $m)) {
                $flags[$m[1]] = true;
            } elseif (preg_match('/^-([a-zA-Z0-9]+)$/', $item, $m)) {
                foreach (str_split($m[1]) as $f) $flags[$f] = true;
            } else {
                $args[] = $item;
            }
        }
        return ['args' => $args, 'options' => $options, 'flags' => $flags];
    }

    /**
     * Output a formatted error block
     */
    public static function errorBlock($msg)
    {
        self::colorEcho("\n[ERROR] ", 'red');
        self::colorEcho($msg . "\n\n", 'yellow');
    }

    /**
     * Output a formatted info block
     */
    public static function infoBlock($msg)
    {
        self::colorEcho("\n[INFO] ", 'cyan');
        self::colorEcho($msg . "\n\n", 'green');
    }

    /**
     * Output a formatted table
     */
    public static function table($headers, $rows)
    {
        $colWidths = array_map('strlen', $headers);
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $colWidths[$i] = max($colWidths[$i], strlen($cell));
            }
        }
        // Print header
        foreach ($headers as $i => $h) {
            self::colorEcho(str_pad($h, $colWidths[$i]), 'cyan');
            echo "  ";
        }
        echo "\n";
        // Print rows
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                echo str_pad($cell, $colWidths[$i]) . "  ";
            }
            echo "\n";
        }
    }

    public static function colorEcho($text, $color = 'default')
    {
        $colors = [
            'default' => "\033[0m",
            'red'     => "\033[31m",
            'green'   => "\033[32m",
            'yellow'  => "\033[33m",
            'blue'    => "\033[34m",
            'magenta' => "\033[35m",
            'cyan'    => "\033[36m",
        ];
        $colorCode = $colors[$color] ?? $colors['default'];
        echo $colorCode . $text . $colors['default'];
    }

    public static function prompt($prompt, $secure = false)
    {
        if ($secure) {
            if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
                echo $prompt;
                return trim(fgets(STDIN));
            } else {
                echo $prompt;
                system('stty -echo');
                $input = trim(fgets(STDIN));
                system('stty echo');
                echo "\n";
                return $input;
            }
        } else {
            echo $prompt;
            return trim(fgets(STDIN));
        }
    }
}


