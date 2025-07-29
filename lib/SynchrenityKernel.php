<?php
namespace Synchrenity;

// SynchrenityKernel: Robust CLI kernel for Synchrenity
class SynchrenityKernel
{
    // --- ADVANCED: Command aliases ---
    protected $aliases = [];
    public function alias($alias, $commandName) { $this->aliases[$alias] = $commandName; }
    public function resolveCommandName($name) {
        return $this->aliases[$name] ?? $name;
    }

    // --- ADVANCED: Command groups/namespaces ---
    protected $groups = [];
    public function group($group, $commandName) {
        $this->groups[$group][] = $commandName;
    }
    public function getGroups() { return $this->groups; }

    // --- ADVANCED: Output formatting (JSON/YAML) ---
    public static function output($data, $format = 'table', $headers = null) {
        if ($format === 'json') {
            echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
        } elseif ($format === 'table' && $headers) {
            self::table($headers, $data);
        } else {
            print_r($data);
        }
    }

    // --- ADVANCED: Logging ---
    protected $logger;
    public function setLogger($logger) { $this->logger = $logger; }
    public function log($level, $msg, $context = []) {
        if ($this->logger) return $this->logger->log($level, $msg, $context);
        error_log("[$level] $msg " . json_encode($context));
    }

    // --- ADVANCED: Command scheduling (cron-like) ---
    protected $schedules = [];
    public function schedule($commandName, $cronExpr) { $this->schedules[] = ['command'=>$commandName,'cron'=>$cronExpr]; }
    public function getSchedules() { return $this->schedules; }

    // --- ADVANCED: Plugin/extension loader ---
    public function loadPlugin($file) {
        if (file_exists($file)) {
            $plugin = include $file;
            if (is_object($plugin) && method_exists($plugin, 'register')) $plugin->register($this);
        }
    }

    // --- ADVANCED: Interactive mode ---
    public function interactive($prompt, callable $handler) {
        while (true) {
            $input = self::prompt($prompt);
            if (in_array($input, ['exit','quit','q'])) break;
            $handler($input, $this);
        }
    }

    // --- ADVANCED: Exit codes ---
    const EXIT_SUCCESS = 0;
    const EXIT_ERROR = 1;
    const EXIT_USAGE = 2;

    // --- ADVANCED: Environment awareness ---
    public function isTTY() { return function_exists('posix_isatty') && posix_isatty(STDOUT); }
    public function isCI() { return getenv('CI') || getenv('CONTINUOUS_INTEGRATION'); }

    // --- ADVANCED: Security (restrict commands) ---
    protected $allowedUsers = [];
    public function allowUser($user) { $this->allowedUsers[] = $user; }
    public function isUserAllowed($user) { return empty($this->allowedUsers) || in_array($user, $this->allowedUsers); }
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
        $commandName = $this->resolveCommandName($args[0]);
        $commandName = preg_replace('/[^a-zA-Z0-9:_-]/', '', $commandName);
        $command = $this->getCommand($commandName);
        if ($command) {
            // Security: restrict by user if set
            $user = get_current_user();
            if (!$this->isUserAllowed($user)) {
                self::errorBlock("User '$user' is not allowed to run this command.");
                return self::EXIT_ERROR;
            }
            // Parse options and flags
            $parsed = $this->parseArgs(array_slice($args, 1));
            try {
                $result = $command->handle($parsed['args'], $parsed['options'], $parsed['flags']);
                // Output formatting
                if (isset($parsed['options']['output'])) {
                    self::output($result, $parsed['options']['output']);
                }
                return is_int($result) ? $result : self::EXIT_SUCCESS;
            } catch (\Throwable $e) {
                $this->log('error', $e->getMessage(), ['exception'=>$e]);
                self::errorBlock($e->getMessage());
                return self::EXIT_ERROR;
            }
        }
        self::errorBlock("Unknown command: $commandName");
        return self::EXIT_USAGE;
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


