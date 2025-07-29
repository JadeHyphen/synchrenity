<?php

declare(strict_types=1);

namespace Synchrenity\Commands;

use Synchrenity\SynchrenityCommand;

class SynchrenityMigrationStatusCommand extends SynchrenityCommand
{
    protected $name        = 'migration:status';
    protected $description = 'Show migration status.';

    public function __construct($kernel = null)
    {
        parent::__construct($kernel);
    }

    public function handle(array $args, array $options, array $flags)
    {
        $this->info('Checking migration status...');
        // ...migration status logic...
        $this->info('Migration status displayed.');

        return 0;
    }
}
