<?php

declare(strict_types=1);

namespace Synchrenity\Commands;

use Synchrenity\SynchrenityCommand;

class SynchrenityHelpCommand extends SynchrenityCommand
{
    protected $name        = 'help';
    protected $description = 'Show help for a command.';
    protected $kernel;
    public function __construct($kernel = null)
    {
        parent::__construct($kernel);
        $this->kernel = $kernel;
    }
    public function handle(array $args, array $options, array $flags)
    {
        $cmd = $args[0] ?? null;

        if (!$cmd) {
            $this->info('Usage: help <command>');

            return 0;
        }
        $commandObj = $this->kernel->getCommand($cmd);

        if ($commandObj) {
            $this->info("Help for '$cmd':");
            $this->info($commandObj->getDescription());

            if (method_exists($commandObj, 'getUsage')) {
                $this->info('Usage: ' . $commandObj->getUsage());
            }

            if (method_exists($commandObj, 'getOptions')) {
                $opts = $commandObj->getOptions();

                if ($opts) {
                    $this->info('Options:');

                    foreach ($opts as $opt => $desc) {
                        $this->info("  --$opt: $desc");
                    }
                }
            }

            if (method_exists($commandObj, 'getFlags')) {
                $flags = $commandObj->getFlags();

                if ($flags) {
                    $this->info('Flags:');

                    foreach ($flags as $flag => $desc) {
                        $this->info("  -$flag: $desc");
                    }
                }
            }
        } else {
            $this->error("Command not found: $cmd");
        }

        return 0;
    }
}
