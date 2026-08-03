<?php

namespace Leaf\Commands;

use Leaf\Sprout\Command;

class DatabaseRollbackCommand extends Command
{
    protected $signature = 'db:rollback
        {file? : Roll back a particular table only}
        {--step|s=1 : The number of versions to roll back, default is 1}';
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

            if ($fileToMigrate && basename($currentFileName, '.yml') !== basename($fileToMigrate, '.yml')) {
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

        $this->info('Database rollback completed!');
        $this->writeln("Your schema files were not changed, so they may now be ahead of your database.\nRun <comment>db:migrate</comment> to re-apply them, or edit them to match the rolled-back state.\n");

        return 0;
    }
}
