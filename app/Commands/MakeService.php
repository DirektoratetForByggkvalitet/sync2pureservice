<?php

namespace App\Commands;

use Illuminate\Console\GeneratorCommand;

/**
 * Installation tip:
 * 1. Put MakeService.php into app/Commands/
 * 2. Put service.stub in app/Console/Stubs/
 *
 * If some of these folders do not exist, create them.
 */
class MakeService extends GeneratorCommand {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:service {name : Class name for the service.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new service class in app/Services';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Class';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return app_path('Console/Stubs/service.stub');
    }


    /**
     * Get the default namespace for the class.
     *
     * @param string $rootNamespace
     *
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\Services';
    }
}
