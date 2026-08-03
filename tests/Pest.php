<?php

/*
|--------------------------------------------------------------------------
| Test harness for leafs/schema
|--------------------------------------------------------------------------
| Schema.php is normally booted by Leaf MVC (mvc-core), which provides the
| StoragePath() global and wires \Leaf\Schema::setDbConnection() with an
| Illuminate Capsule Manager. Here we reproduce that wiring with:
|   - a StoragePath() shim pointing into a per-test sandbox directory
|   - a real Illuminate Capsule Manager on an sqlite file database
| storage(), path() and tick() come from the real leafs/fs and leafs/date
| packages. Leaf\Helpers\Password (used by the @hash seed token) ships with
| leafs/password which is not a dependency, so a tiny shim is provided.
*/

use Illuminate\Database\Capsule\Manager as Capsule;

define('SANDBOX', '/tmp/schema-test-sandbox' . (getenv('TEST_TOKEN') ? '-' . getenv('TEST_TOKEN') : ''));

if (!function_exists('StoragePath')) {
    function StoragePath($path = '', bool $slash = false): string
    {
        return SANDBOX . '/storage/' . $path;
    }
}

require __DIR__ . '/shims/Config.php';
require __DIR__ . '/shims/Password.php';
require __DIR__ . '/shims/Models.php';

function setupSchemaEnv(): void
{
    if (is_dir(SANDBOX)) {
        exec('rm -rf ' . escapeshellarg(SANDBOX));
    }

    mkdir(SANDBOX . '/storage', 0777, true);
    mkdir(SANDBOX . '/app/database', 0777, true);

    touch(SANDBOX . '/db.sqlite');

    $capsule = new Capsule();
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => SANDBOX . '/db.sqlite',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    $capsule->setAsGlobal();

    \Leaf\Schema::setDbConnection($capsule);
}

/** Write a schema yaml file into the sandbox app dir and return its path */
function schemaFile(string $table, string $yaml): string
{
    $file = SANDBOX . "/app/database/$table.yml";
    file_put_contents($file, $yaml);

    return $file;
}

function dbSchema(): \Illuminate\Database\Schema\Builder
{
    return Capsule::schema();
}

/** Column metadata keyed by column name */
function columnInfo(string $table): array
{
    $columns = [];

    foreach (dbSchema()->getColumns($table) as $column) {
        $columns[$column['name']] = $column;
    }

    return $columns;
}

/** History rows for a table, oldest first */
function historyRows(string $table): array
{
    if (!dbSchema()->hasTable('leaf_schema_history')) {
        return [];
    }

    return Capsule::table('leaf_schema_history')
        ->where('table_name', $table)
        ->orderBy('id')
        ->get()
        ->all();
}

uses()->beforeEach(fn () => setupSchemaEnv())->in(__DIR__);
