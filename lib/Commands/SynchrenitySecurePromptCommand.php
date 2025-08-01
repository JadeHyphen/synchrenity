<?php

declare(strict_types=1);

namespace Synchrenity\Commands;

use Synchrenity\SynchrenityCommand;
use Synchrenity\SynchrenityKernel;

class SynchrenitySecurePromptCommand extends SynchrenityCommand
{
    protected string $name       = 'secure:prompt';
    protected string $description = 'Prompt for secure input (e.g., password)';
    protected string $usage      = 'secure:prompt <label>';
    protected array $options    = [];
    protected array $flags      = [];

    public function __construct($kernel = null)
    {
        parent::__construct($kernel);
    }

    public function handle(array $args, array $options, array $flags)
    {
        $label = $args[0] ?? 'Enter secret:';
        $input = SynchrenityKernel::prompt($label, true);
        $this->info('Input received securely.');

        // For demo, do not echo the input
        return 0;
    }
}
