<?php

namespace Leaf\Commands;

use Leaf\Sprout\Command;

class DatabaseRollbackCommand extends Command
{
    protected $signature = 'db:rollback
        {file : The name of the schema file}
        {--step|s=1 : The batch to rollback, default is 1}';
    public $description = 'Rollback database to a previous state';
    public $help = 'Rollback database to a previous state, add -s to time-travel to a specific state.';

    protected function handle()
    {
        $fileToMigrate = $this->argument('file');

        $migrations = function_exists('AppPaths') ?
            glob(getcwd() . DIRECTORY_SEPARATOR . AppPaths('database') . DIRECTORY_SEPARATOR . '*.yml') :
            glob(getcwd() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . '*.yml');

        foreach ($migrations as $migration) {
            $currentFileName = path($migration)->basename();

            if ($fileToMigrate && rtrim($currentFileName, '.yml') !== rtrim($fileToMigrate, '.yml')) {
                continue;
            }

            $this->writeln("> db rollback on <comment>$currentFileName</comment>");

            if (
                !\Leaf\Schema::rollback(
                    $migration,
                    (int) $this->option('step')
                )
            ) {
                $this->error("Could not rollback $currentFileName");

                return 1;
            }
        }

        $this->info("Database rollback completed!\n");

        return 0;
    }
}
