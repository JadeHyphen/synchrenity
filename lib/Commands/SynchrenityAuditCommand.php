<?php

declare(strict_types=1);

namespace Synchrenity\Commands;

use Synchrenity\SynchrenityCommand;

class SynchrenityAuditCommand extends SynchrenityCommand
{
    protected string $name       = 'security:audit';
    protected string $description = 'Run a security audit on your Synchrenity project.';
    protected string $usage      = 'security:audit';
    protected array $options    = [];
    protected array $flags      = [];

    public function __construct($kernel = null)
    {
        parent::__construct($kernel);
    }

    public function handle(array $args, array $options, array $flags)
    {
        $issues = [];

        if (!file_exists(__DIR__ . '/../../.env')) {
            $issues[] = '.env file missing.';
        }

        if (is_writable(__DIR__ . '/../../public')) {
            $issues[] = 'public/ directory is writable.';
        }

        if (empty($issues)) {
            $this->info('No security issues detected.');
        } else {
            $this->error("Security issues found:\n" . implode("\n", $issues));
        }

        return 0;
    }
}
