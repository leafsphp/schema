<?php

namespace Leaf\Commands;

use Leaf\Sprout\Command;

class DatabaseSeedCommand extends Command
{
    protected $signature = 'db:seed
        {file? : The name of the schema file to seed}';
    protected $description = 'Seed the database with records';
    protected $help = 'Seed the database with records';

    protected function handle()
    {
        $fileToSeed = $this->argument('file');

        $seeds = function_exists('AppPaths') ?
            glob(getcwd() . DIRECTORY_SEPARATOR . AppPaths('database') . DIRECTORY_SEPARATOR . '*.yml') :
            glob(getcwd() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . '*.yml');

        foreach ($seeds as $seed) {
            $currentFileName = path($seed)->basename();

            if ($fileToSeed && rtrim($currentFileName, '.yml') !== rtrim($fileToSeed, '.yml')) {
                continue;
            }

            if (!\Leaf\Schema::seed($seed)) {
                $this->error("Could not seed $currentFileName");
                return 1;
            }

            $this->writeln("> $currentFileName seeded successfully!");
        }

        $this->info("Database seeding completed!\n");

        return 0;
    }
}
