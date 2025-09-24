<?php

namespace Leaf\Commands;

class DatabaseCommands
{
    /**
     * Return all commands
     */
    public static function commands()
    {
        return [
            DatabaseDropCommand::class,
            DatabaseMigrationCommand::class,
            DatabaseResetCommand::class,
            DatabaseRollbackCommand::class,
            DatabaseSeedCommand::class,
            DeleteSchemaCommand::class,
            GenerateSchemaCommand::class,
        ];
    }
}
