<?php

declare(strict_types=1);

namespace Synchrenity;

/**
 * Base class for all Synchrenity CLI commands
 */
abstract class SynchrenityCommand
{
    protected string $name        = '';
    protected string $description = '';
    protected $kernel;
    protected array $arguments = [];
    protected array $options   = [];
    protected array $flags     = [];
    protected ?string $group     = null;
    protected array $aliases   = [];
    protected array $hooks     = [];

    public function __construct($kernel = null)
    {
        $this->kernel = $kernel;
    }

    public function getName(): string
    {
        return $this->name;
    }
    public function getDescription(): string
    {
        return $this->description;
    }
    public function getArguments(): array
    {
        return $this->arguments;
    }
    public function getOptions(): array
    {
        return $this->options;
    }
    public function getFlags(): array
    {
        return $this->flags;
    }
    public function getGroup(): ?string
    {
        return $this->group;
    }
    public function getAliases(): array
    {
        return $this->aliases;
    }

    public function addArgument(string $name, string $desc = '', bool $required = false): self
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('Argument name cannot be empty');
        }
        $this->arguments[$name] = ['desc' => $desc,'required' => $required];
        return $this;
    }
    public function addOption(string $name, string $desc = '', $default = null): self
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('Option name cannot be empty');
        }
        $this->options[$name] = ['desc' => $desc,'default' => $default];
        return $this;
    }
    public function addFlag(string $name, string $desc = ''): self
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('Flag name cannot be empty');
        }
        $this->flags[$name] = ['desc' => $desc];
        return $this;
    }
    public function setGroup(string $group): self
    {
        $this->group = $group;
        return $this;
    }
    public function addAlias(string $alias): self
    {
        if (empty($alias)) {
            throw new \InvalidArgumentException('Alias cannot be empty');
        }
        $this->aliases[] = $alias;
        return $this;
    }

    public function addHook(string $event, callable $cb): self
    {
        $this->hooks[$event][] = $cb;
        return $this;
    }
    protected function triggerHook(string $event, ...$args): void
    {
        foreach ($this->hooks[$event] ?? [] as $cb) {
            call_user_func_array($cb, $args);
        }
    }

    /**
     * Main command handler. Must be implemented by subclasses.
     */
    abstract public function handle(array $args, array $options, array $flags);

    // --- ADVANCED: Argument/option/flag validation ---
    public function validateInput($args, $options, $flags)
    {
        foreach ($this->arguments as $name => $meta) {
            if ($meta['required'] && (!isset($args[$name]) || $args[$name] === '')) {
                $this->error("Missing required argument: $name");

                return false;
            }
        }

        return true;
    }

    // --- ADVANCED: Interactive prompts ---
    protected function ask($prompt, $default = null)
    {
        echo $prompt . ($default ? " [$default]" : '') . ': ';
        $input = trim(fgets(STDIN));

        return $input !== '' ? $input : $default;
    }
    protected function confirm($prompt, $default = false)
    {
        $yes = $default ? 'Y/n' : 'y/N';
        echo "$prompt [$yes]: ";
        $input = strtolower(trim(fgets(STDIN)));

        if ($input === '') {
            return $default;
        }

        return in_array($input, ['y','yes']);
    }
    protected function secret($prompt)
    {
        echo $prompt;

        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            $input = trim(fgets(STDIN));
        } else {
            system('stty -echo');
            $input = trim(fgets(STDIN));
            system('stty echo');
            echo "\n";
        }

        return $input;
    }

    // --- ADVANCED: Output formatting ---
    protected function output($data, $format = 'table', $headers = null)
    {
        if ($this->kernel && method_exists($this->kernel, 'output')) {
            $this->kernel->output($data, $format, $headers);
        } else {
            if ($format === 'json') {
                echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
            } elseif ($format === 'table' && $headers) {
                $this->table($headers, $data);
            } else {
                print_r($data);
            }
        }
    }

    // --- ADVANCED: Table output ---
    protected function table($headers, $rows)
    {
        $colWidths = array_map('strlen', $headers);

        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $colWidths[$i] = max($colWidths[$i], strlen($cell));
            }
        }

        foreach ($headers as $i => $h) {
            $this->info(str_pad($h, $colWidths[$i]));
        }

        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                echo str_pad($cell, $colWidths[$i]) . '  ';
            }
            echo "\n";
        }
    }

    // --- ADVANCED: Progress bar ---
    protected function progressBar($total, $callback)
    {
        $width = 40;

        for ($i = 0; $i <= $total; $i++) {
            $done = (int)($width * $i / $total);
            $left = $width - $done;
            $bar  = str_repeat('#', $done) . str_repeat('-', $left);
            printf("\r[%s] %d/%d", $bar, $i, $total);
            $callback($i);
        }
        echo "\n";
    }

    // --- ADVANCED: Logging ---
    protected function log($level, $msg, $context = [])
    {
        if ($this->kernel && method_exists($this->kernel, 'log')) {
            $this->kernel->log($level, $msg, $context);
        } else {
            error_log("[$level] $msg " . json_encode($context));
        }
    }

    // --- ADVANCED: Exit codes ---
    protected function exit($code = 0)
    {
        exit($code);
    }

    // --- ADVANCED: Plugin hooks ---
    public function registerPlugin($plugin)
    {
        if (is_callable([$plugin, 'register'])) {
            $plugin->register($this);
        }
    }

    /**
     * Output helpers for CLI
     */
    protected function info($msg)
    {
        echo "\033[32m$msg\033[0m\n";
    }
    protected function warn($msg)
    {
        echo "\033[33m$msg\033[0m\n";
    }
    protected function error($msg)
    {
        echo "\033[31m$msg\033[0m\n";
    }
    protected function line($msg)
    {
        echo "$msg\n";
    }
}
