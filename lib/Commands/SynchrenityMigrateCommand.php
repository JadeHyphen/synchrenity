<?php

declare(strict_types=1);

namespace Synchrenity\Commands;

use Synchrenity\SynchrenityCommand;

class SynchrenityMigrateCommand extends SynchrenityCommand
{
    protected $name        = 'migrate';
    protected $description = 'Run database migrations.';

    public function __construct($kernel = null)
    {
        parent::__construct($kernel);
    }

    public function handle(array $args, array $options, array $flags)
    {
        $this->info('Running migrations...');
        // ...migration logic...
        $this->info('Migrations complete.');

        return 0;
    }
}
