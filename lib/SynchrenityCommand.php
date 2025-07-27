<?php
namespace Synchrenity;

/**
 * Base class for all Synchrenity CLI commands
 */
abstract class SynchrenityCommand
{
    protected $name = '';
    protected $description = '';
    protected $kernel;

    public function __construct($kernel = null)
    {
        $this->kernel = $kernel;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Main command handler. Must be implemented by subclasses.
     */
    abstract public function handle(array $args, array $options, array $flags);

    /**
     * Output helpers for CLI
     */
    protected function info($msg) { echo "\033[32m$msg\033[0m\n"; }
    protected function warn($msg) { echo "\033[33m$msg\033[0m\n"; }
    protected function error($msg) { echo "\033[31m$msg\033[0m\n"; }
    protected function line($msg) { echo "$msg\n"; }
}
