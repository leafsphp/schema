<?php

namespace Leaf\Commands;

use Illuminate\Support\Str;
use Leaf\Sprout\Command;

class DeleteSchemaCommand extends Command
{
    protected $signature = 'd:schema
        {schema : The name of the schema file}';
    protected $description = 'Delete a schema file';
    protected $help = 'Delete a schema file';

    protected function handle()
    {
        $schema = Str::lower($this->argument('schema'));

        if (strpos($schema, 'schema')) {
            $schema = str::plural(str_replace('Schema', '', $schema));
        }

        $file = function_exists('DatabasePath') ?
            getcwd() . DIRECTORY_SEPARATOR . DatabasePath("$schema.yml") :
            getcwd() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . "$schema.yml";

        if (!\Leaf\FS\File::exists($file)) {
            $this->error("$schema doesn't exist!");
            return 1;
        }

        if (!\Leaf\FS\File::delete($file)) {
            $this->error("Couldn't delete $file, you might need to remove it manually.");
            return 1;
        }

        $this->comment("$schema deleted successfully");

        if (\Leaf\FS\Directory::isEmpty($dirname = dirname($file))) {
            if (sprout()->confirm("> $dirname is empty. Delete folder?")) {
                if (\Leaf\FS\Directory::delete($dirname)) {
                    $this->comment("$dirname deleted successfully!");
                }
            }
        }

        return 0;
    }
}
