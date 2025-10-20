<?php

namespace Leaf\Commands;

use Leaf\Sprout\Command;
use Illuminate\Support\Str;

class DeleteModelCommand extends Command
{
    protected $signature = 'd:model {model : The name of the model}';
    protected $description = 'Delete a model';
    protected $help = 'Delete a model file';

    protected function handle()
    {
        $model = Str::studly($this->argument('model'));

        $file = function_exists('ModelsPath')
            ? getcwd() . DIRECTORY_SEPARATOR . ModelsPath("$model.php")
            : getcwd() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . "$model.php";

        if (!\Leaf\FS\File::exists($file)) {
            $this->error("$model doesn't exist!");
            return 1;
        }

        if (!\Leaf\FS\File::delete($file)) {
            $this->error("Couldn't delete $file, you might need to remove it manually.");
            return 1;
        }

        $this->comment("$model deleted successfully");

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
