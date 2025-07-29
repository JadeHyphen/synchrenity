<?php

declare(strict_types=1);

namespace Synchrenity\Commands;

use Synchrenity\SynchrenityCommand;

class SynchrenityRollbackCommand extends SynchrenityCommand
{
    protected $name        = 'rollback';
    protected $description = 'Rollback the last database migration.';

    public function __construct($kernel = null)
    {
        parent::__construct($kernel);
    }

    public function handle(array $args, array $options, array $flags)
    {
        $this->info('Rolling back last migration...');
        // ...rollback logic...
        $this->info('Rollback complete.');

        return 0;
    }
}
