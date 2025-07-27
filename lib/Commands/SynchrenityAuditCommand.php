<?php
namespace Synchrenity\Commands;

use Synchrenity\SynchrenityCommand;
use Synchrenity\SynchrenityKernel;

class SynchrenityAuditCommand extends SynchrenityCommand
{
    protected $name = 'security:audit';
    protected $description = 'Run a security audit on your Synchrenity project.';
    protected $usage = 'security:audit';
    protected $options = [];
    protected $flags = [];

    public function __construct($kernel = null) {
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
