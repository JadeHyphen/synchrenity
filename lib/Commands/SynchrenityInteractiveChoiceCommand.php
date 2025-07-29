<?php

declare(strict_types=1);

namespace Synchrenity\Commands;

use Synchrenity\SynchrenityCommand;
use Synchrenity\SynchrenityKernel;

class SynchrenityInteractiveChoiceCommand extends SynchrenityCommand
{
    protected $name        = 'interactive:choice';
    protected $description = 'Prompt user to select from multiple choices.';
    protected $usage       = 'interactive:choice <question> [choices...]';
    protected $options     = [];
    protected $flags       = [];

    public function __construct($kernel = null)
    {
        parent::__construct($kernel);
    }

    public function handle(array $args, array $options, array $flags)
    {
        $question = $args[0] ?? 'Choose:';
        $choices  = array_slice($args, 1);

        if (empty($choices)) {
            $choices = ['Yes', 'No'];
        }
        SynchrenityKernel::colorEcho($question . "\n", 'cyan');

        foreach ($choices as $i => $choice) {
            echo '  [' . ($i + 1) . "] $choice\n";
        }
        $selected = null;

        while ($selected === null) {
            $input = SynchrenityKernel::prompt('Enter choice number: ');

            if (is_numeric($input) && isset($choices[$input - 1])) {
                $selected = $choices[$input - 1];
            } else {
                $this->error('Invalid choice.');
            }
        }
        $this->info("You selected: $selected");

        return 0;
    }
}
