<?php

namespace Leaf\Commands;

use Illuminate\Support\Str;
use Leaf\Sprout\Command;

class GenerateSchemaCommand extends Command
{
    protected $signature = 'g:schema
        {schema : The name of the schema file}
        {--all? : Create a model, controller, and view file for your schema}
        {--model? : Create a model for your schema file}
        {--view? : Create a view for your schema file}
        {--controller? : Create a controller for your schema file}';
    protected $description = 'Create a new schema file';
    protected $help = 'Generate a new database schema file';

    protected function handle()
    {
        $schema = Str::lower($this->argument('schema'));

        if (strpos($schema, 'schema')) {
            $schema = str::plural(str_replace('Schema', '', $schema));
        }

        $schemaFile = function_exists('DatabasePath') ?
            getcwd() . DIRECTORY_SEPARATOR . DatabasePath("$schema.yml") :
            getcwd() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . "$schema.yml";

        if (file_exists($schemaFile)) {
            $this->error("$schema already exists");
            return 1;
        }

        if (
            \Leaf\FS\File::create($schemaFile, function () {
                return file_get_contents(__DIR__ . '/stubs/schema.stub');
            }, ['recursive' => true])
        ) {
            $this->comment("$schema schema file created successfully!");
            $this->generateExtraFiles($schema);
        }

        return 0;
    }

    protected function generateExtraFiles($modelName)
    {
        if ($this->option('model') || $this->option('all')) {
            $process = sprout()->run("php leaf g:model $modelName");

            $this->comment(
                $process === 0 ?
                'Model generated successfully!' :
                '<error>Couldn\'t generate model</error>'
            );
        }

        if ($this->option('view') || $this->option('all')) {
            $process = sprout()->run("php leaf g:template $modelName");

            $this->comment(
                $process === 0 ?
                'View file generated successfully!' :
                '<error>Couldn\'t generate view file</error>'
            );
        }

        if ($this->option('controller') || $this->option('all')) {
            $process = sprout()->run("php leaf g:controller $modelName");

            $this->comment(
                $process === 0 ?
                'Controller generated successfully!' :
                '<error>Couldn\'t generate controller</error>'
            );
        }
    }
}
