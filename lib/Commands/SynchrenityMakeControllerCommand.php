<?php

declare(strict_types=1);

namespace Synchrenity\Commands;

use Synchrenity\SynchrenityCommand;

class SynchrenityMakeControllerCommand extends SynchrenityCommand
{
    protected $name        = 'make:controller';
    protected $description = 'Generate a new controller class.';
    protected $usage       = 'make:controller <Name> [--resource] [--force]';
    protected $options     = [
        'resource' => 'Generate a resource controller',
        'force'    => 'Overwrite if file exists',
    ];
    protected $flags = [];

    public function __construct($kernel = null)
    {
        parent::__construct($kernel);
    }

    public function handle(array $args, array $options, array $flags)
    {
        $name = $args[0] ?? null;

        if (!$name) {
            $this->error('Controller name required.');

            return 1;
        }
        $resource = isset($options['resource']);
        $force    = isset($options['force']);
        $file     = __DIR__ . '/../Controllers/' . $name . 'Controller.php';

        if (file_exists($file) && !$force) {
            $this->error('File exists. Use --force to overwrite.');

            return 1;
        }
        $stub = "<?php\nnamespace App\\Controllers;\n\nclass {$name}Controller\n{\n    // ...\n}";
        file_put_contents($file, $stub);
        $this->info("Controller '$name' created successfully.");

        return 0;
    }
}
