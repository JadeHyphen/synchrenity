<?php
namespace Synchrenity\Commands;

use Synchrenity\SynchrenityCommand;
use Synchrenity\SynchrenityKernel;

class SynchrenityListCommand extends SynchrenityCommand
{
    protected $name = 'list';
    protected $description = 'List all available commands.';
    protected $kernel;
    public function __construct($kernel = null) { parent::__construct($kernel); $this->kernel = $kernel; }
    public function handle(array $args, array $options, array $flags)
    {
        $headers = ['Command', 'Description'];
        $rows = [];
        foreach ($this->kernel->getCommandNames() as $cmd) {
            $obj = $this->kernel->getCommand($cmd);
            $rows[] = [$cmd, $obj ? $obj->getDescription() : ''];
        }
        SynchrenityKernel::table($headers, $rows);
        $this->info("Use 'help <command>' for details.");
        return 0;
    }
}
