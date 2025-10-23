<?php

namespace Leaf\Commands;

use Leaf\Sprout\Command;

class DatabaseResetCommand extends Command
{
    protected $signature = 'db:reset
        {file? : The name of the schema file to reset}
        {--seed|s? : Seed the database after migration}';
    public $description = 'Reset migration history + db tables';
    public $help = 'Clear all database tables, and migrate afresh. Add --seed to seed db';

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

            $this->writeln("> db reset on <comment>$currentFileName</comment>");

            if (!\Leaf\Schema::reset($migration)) {
                $this->error("Could not reset $currentFileName");
                return 1;
            }

            if ($this->option('seed')) {
                if (!\Leaf\Schema::seed($migration)) {
                    $this->error("Could not seed $currentFileName");
                    return 1;
                }

                $this->writeln("> $currentFileName seeded successfully!");
            }
        }

        $this->info("Database reset completed!\n");

        return 0;
    }
}
