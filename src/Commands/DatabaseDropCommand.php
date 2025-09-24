<?php

namespace Leaf\Commands;

use Leaf\Sprout\Command;

class DatabaseDropCommand extends Command
{
    protected $signature = 'db:drop
        {file? : Drop the table associated with a particular file}';
    protected $description = 'Drop database tables and reset migration history';
    protected $help = 'Drop all database tables and reset migration history. You can specify a file to drop only that migration.';

    protected function handle()
    {
        $fileToMigrate = $this->argument('file');

        $migrations = function_exists('AppPaths') ?
            glob(getcwd() . DIRECTORY_SEPARATOR . AppPaths('database') . DIRECTORY_SEPARATOR . '*.yml') :
            glob(getcwd() . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . '*.yml');

        foreach ($migrations as $migration) {
            $currentFileName = path($migration)->basename();

            if ($fileToMigrate && rtrim($currentFileName, '.yml') !== rtrim($fileToMigrate, '.yml')) {
                continue;
            }

            $this->writeln("> db drop on <comment>$currentFileName</comment>");

            if (!\Leaf\Schema::drop($migration)) {
                $this->error("Could not drop $currentFileName");
                return 1;
            }
        }

        $this->info("Database drop completed!\n");

        return 0;
    }
}
