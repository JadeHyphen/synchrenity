<?php

declare(strict_types=1);

namespace Synchrenity\Commands;

use Synchrenity\SynchrenityCommand;

class SynchrenityMakeMigrationCommand extends SynchrenityCommand
{
    protected string $name       = 'make:migration';
    protected string $description = 'Create a new migration file.';

    public function __construct($kernel = null)
    {
        parent::__construct($kernel);
    }

    public function handle(array $args, array $options, array $flags)
    {
        $this->info('Creating new migration file...');
        // ...migration file creation logic...
        $this->info('Migration file created.');

        return 0;
    }
}
