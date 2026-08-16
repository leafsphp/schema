<?php

namespace Leaf;

use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Symfony\Component\Yaml\Yaml;

/**
 * Leaf DB Schema [WIP]
 * ---
 * One file to rule them all.
 *
 * @version 1.0
 */
class Schema
{
    /** @var \Illuminate\Database\Capsule\Manager */
    protected static Manager $connection;

    /**
     * Get the database connection used in a schema file
     * @param string $fileName The schema file name
     * @return string|null
     */
    public static function getConnection(string $fileName): ?string
    {
        return Yaml::parseFile($fileName)['connection'] ?? null;
    }

    /**
     * Migrate your schema file tables
     * @param string $fileToMigrate The schema file to migrate
     * @return bool
     */
    public static function migrate(string $fileToMigrate): bool
    {
        $data = Yaml::parseFile($fileToMigrate);
        $tableName = basename($fileToMigrate, '.yml');

        $currentConnection = $data['connection'] ?? null;

        try {
            if (!static::$connection::schema($currentConnection)->hasTable($tableName)) {
                static::history($currentConnection)->where('table_name', $tableName)->delete();

                static::$connection::schema($currentConnection)->create($tableName, function (Blueprint $table) use ($data) {
                    $columns = $data['columns'] ?? [];
                    $relationships = $data['relationships'] ?? [];

                    $increments = $data['increments'] ?? true;
                    $timestamps = $data['timestamps'] ?? true;
                    $softDeletes = $data['softDeletes'] ?? false;
                    $rememberToken = $data['remember_token'] ?? false;

                    if ($increments) {
                        $table->increments('id');
                    }

                    foreach ($relationships as $model) {
                        if (strpos($model, 'App\Models') === false) {
                            $model = "App\Models\\$model";
                        }

                        $table->foreignIdFor($model);
                    }

                    foreach ($columns as $columnName => $columnValue) {
                        static::createColumn($table, $columnName, $columnValue);
                    }

                    if ($rememberToken) {
                        $table->rememberToken();
                    }

                    if ($softDeletes) {
                        $table->softDeletes();
                    }

                    if ($timestamps) {
                        $table->timestamps();
                    }
                });

                static::recordState($tableName, $data, $currentConnection);
            } else {
                $lastMigration = static::lastAppliedState($tableName, $currentConnection);

                // table exists but has no history (created outside schema
                // files): adopt the current schema file as its baseline
                // without altering anything
                if ($lastMigration === null) {
                    static::recordState($tableName, $data, $currentConnection);

                    return true;
                }

                // nothing changed since the last applied state
                if ($lastMigration === $data) {
                    return true;
                }

                static::applyChanges($tableName, $data, $lastMigration, $currentConnection);
                static::recordState($tableName, $data, $currentConnection);
            }
        } catch (\Throwable $th) {
            throw $th;
        }

        return true;
    }

    /**
     * Apply the diff between a desired table state and the last applied state
     * @param string $tableName The table to alter
     * @param array $data The desired state (parsed schema file)
     * @param array $lastMigration The last applied state to diff against
     * @param string|null $currentConnection The connection to run on
     * @return void
     */
    protected static function applyChanges(string $tableName, array $data, array $lastMigration, ?string $currentConnection = null): void
    {
        static::$connection::schema($currentConnection)->table($tableName, function (Blueprint $table) use ($currentConnection, $data, $tableName, $lastMigration) {
            $columns = $data['columns'] ?? [];
            $relationships = $data['relationships'] ?? [];

            $increments = $data['increments'] ?? true;
            $timestamps = $data['timestamps'] ?? true;
            $softDeletes = $data['softDeletes'] ?? false;
            $rememberToken = $data['remember_token'] ?? false;

            if ($increments !== ($lastMigration['increments'] ?? true)) {
                if ($increments && !static::$connection::schema($currentConnection)->hasColumn($tableName, 'id')) {
                    $table->increments('id');
                } elseif (!$increments && static::$connection::schema($currentConnection)->hasColumn($tableName, 'id')) {
                    $table->dropColumn('id');
                }
            }

            if ($relationships !== ($lastMigration['relationships'] ?? [])) {
                $newRelationships = array_diff($relationships, $lastMigration['relationships'] ?? []);
                $removedRelationships = array_diff($lastMigration['relationships'] ?? [], $relationships);

                foreach ($newRelationships as $model) {
                    if (strpos($model, 'App\Models') === false) {
                        $model = "App\Models\\$model";
                    }

                    $table->foreignIdFor($model);
                }

                foreach ($removedRelationships as $model) {
                    if (strpos($model, 'App\Models') === false) {
                        $model = "App\Models\\$model";
                    }

                    $foreignKey = static::$connection::getForeignKeyName($tableName, $model::getForeignKey());

                    if (static::$connection::schema($currentConnection)->hasColumn($tableName, $foreignKey)) {
                        $table->dropForeign($foreignKey);
                        $table->dropColumn($model::getForeignKey());
                    }
                }
            }

            $columnsDiff = [];
            $staticColumns = [];
            $removedColumns = [];

            foreach ($lastMigration['columns'] ?? [] as $colKey => $colVal) {
                if (!array_key_exists($colKey, $columns)) {
                    $removedColumns[] = $colKey;
                } elseif (static::getColumnAttributes($colVal) !== static::getColumnAttributes($columns[$colKey])) {
                    $columnsDiff[] = $colKey;
                    $staticColumns[] = $colKey;
                } else {
                    $staticColumns[] = $colKey;
                }
            }

            if ($rememberToken !== ($lastMigration['remember_token'] ?? false)) {
                if ($rememberToken && !static::$connection::schema($currentConnection)->hasColumn($tableName, 'remember_token')) {
                    $table->rememberToken();
                } elseif (!$rememberToken && static::$connection::schema($currentConnection)->hasColumn($tableName, 'remember_token')) {
                    $table->dropRememberToken();
                }
            }

            if ($softDeletes !== ($lastMigration['softDeletes'] ?? false)) {
                if ($softDeletes && !static::$connection::schema($currentConnection)->hasColumn($tableName, 'deleted_at')) {
                    $table->softDeletes();
                } elseif (!$softDeletes && static::$connection::schema($currentConnection)->hasColumn($tableName, 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            }

            if ($timestamps !== ($lastMigration['timestamps'] ?? true)) {
                if ($timestamps && !static::$connection::schema($currentConnection)->hasColumn($tableName, 'created_at')) {
                    $table->timestamps();
                } elseif (!$timestamps && static::$connection::schema($currentConnection)->hasColumn($tableName, 'created_at')) {
                    $table->dropTimestamps();
                }
            }

            if (count($removedColumns) > 0) {
                foreach ($removedColumns as $removedColumn) {
                    if (static::$connection::schema($currentConnection)->hasColumn($tableName, $removedColumn)) {
                        $table->dropColumn($removedColumn);
                    }
                }
            }

            $newColumns = array_diff(array_keys($columns), $staticColumns);

            if (count($newColumns) > 0) {
                foreach ($newColumns as $newColumn) {
                    $column = static::getColumnAttributes($columns[$newColumn]);

                    if (!static::$connection::schema($currentConnection)->hasColumn($tableName, $newColumn)) {
                        static::createColumn($table, $newColumn, $column);
                    }
                }
            }

            if (count($columnsDiff) > 0) {
                foreach ($columnsDiff as $changedColumn) {
                    $column = static::getColumnAttributes($columns[$changedColumn]);
                    $prevMigrationColumn = static::getColumnAttributes($lastMigration['columns'][$changedColumn] ?? []);

                    if ($column['type'] === 'timestamp') {
                        continue;
                    }

                    $newCol = static::createColumn(
                        $table,
                        $changedColumn,
                        $column,
                        false
                    );

                    foreach ($column as $columnOptionName => $columnOptionValue) {
                        if ($columnOptionValue === $prevMigrationColumn[$columnOptionName]) {
                            continue;
                        }

                        if ($columnOptionName === 'unique') {
                            if ($columnOptionValue) {
                                $newCol->unique()->change();
                            } else {
                                $table->dropUnique("{$tableName}_{$changedColumn}_unique");
                            }

                            continue;
                        }

                        if ($columnOptionName === 'index') {
                            if ($columnOptionValue) {
                                $newCol->index()->change();
                            } else {
                                $table->dropIndex("{$tableName}_{$changedColumn}_index");
                            }

                            continue;
                        }

                        // skipping this for now, primary + autoIncrement
                        // doesn't work well in the same run. They need to be
                        // run separately for some reason
                        // if ($columnOptionName === 'autoIncrement') {

                        if ($columnOptionName === 'primary') {
                            if ($columnOptionValue) {
                                $newCol->primary()->change();
                            } else {
                                $table->dropPrimary("{$tableName}_{$changedColumn}_primary");
                            }

                            continue;
                        }

                        if ($columnOptionName === 'default') {
                            $newCol->default($columnOptionValue)->change();

                            continue;
                        }

                        if (is_bool($columnOptionValue)) {
                            if ($columnOptionValue) {
                                $newCol->{$columnOptionName}()->change();
                            } else {
                                $newCol->{$columnOptionName}(false)->change();
                            }
                        } else {
                            $newCol->{$columnOptionName}($columnOptionValue)->change();
                        }
                    }

                    $newCol->change();
                }
            }
        });
    }

    /**
     * Seed a database table from schema file
     * @param string $fileToSeed The name of the schema file
     * @return bool
     */
    public static function seed(string $fileToSeed): bool
    {
        $data = Yaml::parseFile($fileToSeed);
        $tableName = basename($fileToSeed, '.yml');

        if (!isset($data['seeds'])) {
            return true;
        }

        $seeds = $data['seeds'] ?? [];
        $currentConnection = $data['connection'] ?? null;

        $count = $seeds['count'] ?? 1;
        $seedsData = $seeds['data'] ?? [];
        $seedsModel = $seeds['model'] ?? null;

        $timestamps = $data['timestamps'] ?? true;
        $softDeletes = $data['softDeletes'] ?? false;
        $rememberToken = $data['remember_token'] ?? false;

        $finalDataToSeed = [];

        if ($seeds['truncate'] ?? false) {
            static::$connection::table($tableName, null, $currentConnection)->truncate();
        }

        if (empty($seedsData) && !$seedsModel) {
            $seedsModel = \Illuminate\Support\Str::studly(
                \Illuminate\Support\Str::singular($tableName)
            );
        }

        if ($seedsModel && (int) $count > 0) {
            if (strpos($seedsModel, 'App\Models') === false) {
                $seedsModel = "App\Models\\$seedsModel";
            }

            if (!class_exists($seedsModel)) {
                throw new \Exception("The model $seedsModel does not exist");
            }

            for ($i = 0; $i < $count; $i++) {
                $finalDataToSeed[] = $seedsModel::__seeder();
            }
        } elseif (is_array($seedsData[0] ?? null)) {
            $faker = static::seedFaker($seeds);

            foreach ($seedsData as $row) {
                $finalDataToSeed[] = array_map(
                    fn ($value) => static::resolveSeedToken($value, $faker),
                    $row
                );
            }
        } else {
            $faker = static::seedFaker($seeds);

            for ($i = 0; $i < $count; $i++) {
                $parsedData = [];

                foreach ($seedsData as $key => $value) {
                    $parsedData[$key] = static::resolveSeedToken($value, $faker);
                }

                $finalDataToSeed[] = $parsedData;
            }
        }

        foreach ($finalDataToSeed as $itemToSeed) {
            if ($rememberToken) {
                $itemToSeed['remember_token'] = \Illuminate\Support\Str::random(10);
            }

            if ($softDeletes) {
                $itemToSeed['deleted_at'] = null;
            }

            if ($timestamps) {
                $itemToSeed['created_at'] = tick()->format('YYYY-MM-DD HH:mm:ss');
                $itemToSeed['updated_at'] = tick()->format('YYYY-MM-DD HH:mm:ss');
            }

            static::$connection::table($tableName, null, $currentConnection)->insert($itemToSeed);
        }

        return true;
    }

    /**
     * Reset a database table
     * @param string $fileToReset The schema file to reset
     * @return bool
     */
    public static function reset(string $fileToReset): bool
    {
        static::drop($fileToReset);

        return static::migrate($fileToReset);
    }

    /**
     * Drop a database table
     * @param string $fileToDrop The schema file to drop
     * @return bool
     */
    public static function drop(string $fileToDrop): bool
    {
        $data = Yaml::parseFile($fileToDrop);
        $tableName = basename($fileToDrop, '.yml');

        $currentConnection = $data['connection'] ?? null;

        try {
            if (static::$connection::schema($currentConnection)->hasTable($tableName)) {
                static::$connection::schema($currentConnection)->dropIfExists($tableName);

                static::history($currentConnection)->where('table_name', $tableName)->delete();
            }

            return true;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * Rollback db to a previous state
     * @param string $fileToRollback The schema file to rollback
     * @param int $step The number of steps to rollback
     * @return bool
     */
    public static function rollback(string $fileToRollback, int $step = 1): bool
    {
        $tableName = basename($fileToRollback, '.yml');
        $currentConnection = static::getConnection($fileToRollback);

        static::importStorageSnapshots($tableName, $currentConnection);

        $history = static::history($currentConnection)
            ->where('table_name', $tableName)
            ->orderBy('id')
            ->get()
            ->all();

        // the newest history row is the currently applied state, so going
        // back $step versions means targeting the row $step places behind it
        $target = $history[count($history) - 1 - $step] ?? null;

        if (!$target) {
            return false;
        }

        $head = end($history);

        static::applyChanges(
            $tableName,
            Yaml::parse($target->schema),
            Yaml::parse($head->schema),
            $currentConnection
        );

        // the target row becomes the head again; the schema file is left
        // untouched — run db:migrate to re-apply it, or edit it to match
        static::history($currentConnection)
            ->where('table_name', $tableName)
            ->where('id', '>', $target->id)
            ->delete();

        return true;
    }

    /**
     * Build the faker instance shared by a seed run (one instance per run,
     * so modifiers like unique() hold across every generated row)
     * @param array $seeds The parsed seeds section of a schema file
     * @return \Faker\Generator
     */
    protected static function seedFaker(array $seeds): \Faker\Generator
    {
        return \Faker\Factory::create($seeds['locale'] ?? \Faker\Factory::DEFAULT_LOCALE);
    }

    /**
     * Resolve an @token seed value. Tokens mirror the PHP call exactly:
     *   '@faker.numberBetween(18, 65)'
     *   '@faker.unique.safeEmail'
     *   '@faker.randomElement(["free", "pro"])'
     *   '@tick.subtract(30, "day").format("YYYY-MM-DD")'
     *   '@randomString(32)'  '@hash("secret")'
     * Arguments are parsed as JSON, so numbers, booleans, strings and arrays
     * all arrive with the right types. Values that aren't recognised tokens
     * pass through unchanged. The pre-v5 colon syntax ('@faker.date:Y-m-d')
     * still works.
     * @param mixed $value The seed value to resolve
     * @param \Faker\Generator|null $faker The faker instance to resolve against
     * @return mixed
     */
    public static function resolveSeedToken($value, ?\Faker\Generator $faker = null)
    {
        if (!is_string($value) || strpos($value, '@') !== 0) {
            return $value;
        }

        $faker = $faker ?? \Faker\Factory::create();

        // anything not rooted in a known token ('@company.com', plain
        // strings that happen to start with @) is a literal value
        preg_match('/^@([A-Za-z_][A-Za-z0-9_]*)/', $value, $rootMatch);

        if (!in_array($rootMatch[1] ?? '', ['faker', 'tick', 'randomString', 'hash'])) {
            return $value;
        }

        // pre-v5 colon syntax
        if (strpos($value, '(') === false && strpos($value, ':') !== false) {
            return static::resolveLegacySeedToken($value, $faker);
        }

        try {
            $segments = static::parseCallChain(substr($value, 1));
        } catch (\InvalidArgumentException $th) {
            throw new \Exception("Could not parse seed token '$value': {$th->getMessage()}");
        }

        [$rootName, $rootArgs] = array_shift($segments);

        switch ($rootName) {
            case 'faker':
                $target = $faker;

                break;
            case 'tick':
                $target = tick(...($rootArgs ?? []));

                break;
            case 'randomString':
                $target = \Illuminate\Support\Str::random($rootArgs[0] ?? 10);

                break;
            case 'hash':
                $target = \Leaf\Helpers\Password::hash($rootArgs[0] ?? 'password');

                break;
            default:
                // not a token ('@company.com', ...): treat as a literal value
                return $value;
        }

        foreach ($segments as [$name, $args]) {
            if ($args !== null) {
                $target = $target->{$name}(...$args);

                continue;
            }

            // no parentheses: no-arg call (faker deprecated property-style
            // access in 1.14), falling back to property access
            try {
                $target = $target->{$name}();
            } catch (\Throwable $th) {
                $target = $target->{$name};
            }
        }

        return $target;
    }

    /**
     * Split a token expression into [name, args|null] call segments.
     * Dots only separate segments outside parentheses and quotes, so
     * arguments can safely contain anything.
     * @param string $expression The expression after the leading @
     * @return array
     */
    protected static function parseCallChain(string $expression): array
    {
        $segments = [];
        $current = '';
        $depth = 0;
        $inString = null;

        $chars = str_split($expression);

        foreach ($chars as $index => $char) {
            if ($inString) {
                $current .= $char;

                if ($char === $inString && ($chars[$index - 1] ?? '') !== '\\') {
                    $inString = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $inString = $char;
                $current .= $char;

                continue;
            }

            if ($char === '(' || $char === '[') {
                $depth++;
            } elseif ($char === ')' || $char === ']') {
                $depth--;
            }

            if ($char === '.' && $depth === 0) {
                $segments[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if ($depth !== 0 || $inString) {
            throw new \InvalidArgumentException('unbalanced parentheses or quotes');
        }

        $segments[] = $current;

        return array_map(function ($segment) {
            if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)(?:\((.*)\))?$/s', trim($segment), $matches)) {
                throw new \InvalidArgumentException("invalid segment '$segment'");
            }

            return [$matches[1], isset($matches[2]) ? static::parseCallArgs($matches[2]) : null];
        }, $segments);
    }

    /**
     * Parse an argument list as JSON (single-quoted strings are converted)
     * @param string $args The raw text between parentheses
     * @return array
     */
    protected static function parseCallArgs(string $args): array
    {
        if (trim($args) === '') {
            return [];
        }

        $parsed = json_decode("[$args]", true);

        if ($parsed === null) {
            $parsed = json_decode('[' . str_replace("'", '"', $args) . ']', true);
        }

        if ($parsed === null) {
            throw new \InvalidArgumentException("could not parse arguments '($args)'");
        }

        return $parsed;
    }

    /**
     * Pre-v5 colon-style seed tokens ('@faker.date:Y-m-d', '@randomString:16')
     * @param string $value The token to resolve
     * @param \Faker\Generator $faker The faker instance to resolve against
     * @return mixed
     */
    protected static function resolveLegacySeedToken(string $value, \Faker\Generator $faker)
    {
        $valueArray = explode('.', $value);

        if ($valueArray[0] === '@faker') {
            $localFakerInstance = $faker;

            foreach ($valueArray as $index => $fakerMethod) {
                if ($index === 0) {
                    continue;
                }

                if (strpos($fakerMethod, ':') !== false) {
                    $fakerMethod = explode(':', $fakerMethod);
                    if (strpos($fakerMethod[1], ',') !== false) {
                        $array_params = explode(',', $fakerMethod[1]);
                        $localFakerInstance = $localFakerInstance->{$fakerMethod[0]}($array_params);
                    } else {
                        $localFakerInstance = $localFakerInstance->{$fakerMethod[0]}($fakerMethod[1]);
                    }
                } else {
                    $localFakerInstance = $localFakerInstance->{$fakerMethod}();
                }
            }

            return is_array($localFakerInstance) ? implode('-', $localFakerInstance) : $localFakerInstance;
        }

        if ($valueArray[0] === '@tick') {
            $localTickInstance = tick();

            foreach ($valueArray as $index => $tickMethod) {
                if ($index === 0) {
                    continue;
                }

                if (strpos($tickMethod, ':') !== false) {
                    $tickMethod = explode(':', $tickMethod);
                    $localTickInstance = $localTickInstance->{$tickMethod[0]}($tickMethod[1]);
                } else {
                    $localTickInstance = $localTickInstance->{$tickMethod}();
                }
            }

            return $localTickInstance;
        }

        if (strpos($value, '@randomString') === 0) {
            $value = explode(':', $value);

            return \Illuminate\Support\Str::random($value[1] ?? 10);
        }

        if (strpos($value, '@hash') === 0) {
            $value = explode(':', $value);

            return \Leaf\Helpers\Password::hash($value[1] ?? 'password');
        }

        return $value;
    }

    /**
     * Get all column attributes
     * @param mixed $value
     * @return array
     */
    public static function getColumnAttributes($value)
    {
        $attributes = [
            'type' => 'string',
            'length' => null,
            'nullable' => false,
            'default' => null,
            'unsigned' => false,
            'index' => false,
            'unique' => false,
            'primary' => false,
            'foreign' => false,
            'foreignTable' => null,
            'foreignColumn' => null,
            'values' => null,
            'onDelete' => null,
            'onUpdate' => null,
            'comment' => null,
            'autoIncrement' => false,
            'useCurrent' => false,
            'useCurrentOnUpdate' => false,
            'charset' => null,
            'collation' => null,
        ];

        if (is_string($value)) {
            $attributes['type'] = $value;
        } elseif (is_array($value)) {
            $attributes = array_merge($attributes, $value);
        }

        return $attributes;
    }

    protected static function createColumn($table, $columnName, $columnValue, $createOnly = true)
    {
        if (is_string($columnValue)) {
            return $table->{$columnValue}($columnName);
        }

        if (is_array($columnValue)) {
            if ($columnValue['type'] === 'string' || $columnValue['type'] === 'char' || $columnValue['type'] === 'text') {
                $returnedColumn = $table->{$columnValue['type']}(
                    $columnName,
                    $columnValue['length'] ?? null
                );

                unset($columnValue['length']);
            } elseif ($columnValue['type'] === 'enum' || $columnValue['type'] === 'set') {
                $returnedColumn = $table->{$columnValue['type']}(
                    $columnName,
                    $columnValue['values'] ?? []
                );

                unset($columnValue['values']);
            } else {
                $returnedColumn = $table->{$columnValue['type']}($columnName);
            }

            unset($columnValue['type']);

            if ($createOnly === true) {
                foreach ($columnValue as $columnOptionName => $columnOptionValue) {
                    if (!is_bool($columnOptionValue) || $columnOptionName === 'default') {
                        $returnedColumn->{$columnOptionName}($columnOptionValue);
                    } else {
                        if ($columnOptionValue) {
                            $returnedColumn->{$columnOptionName}();
                        }
                    }
                }
            }

            return $returnedColumn;
        }
    }

    /**
     * Query builder for the schema history table, creating it if needed.
     * History lives in the same connection as the tables it describes, so
     * every environment (and every connection) is its own source of truth.
     * @param string|null $connection The connection to use
     * @return \Illuminate\Database\Query\Builder
     */
    protected static function history(?string $connection = null)
    {
        $schema = static::$connection::schema($connection);

        if (!$schema->hasTable('leaf_schema_history')) {
            $schema->create('leaf_schema_history', function (Blueprint $table) {
                $table->increments('id');
                $table->string('table_name')->index();
                $table->text('schema');
                $table->timestamp('created_at')->nullable();
            });
        }

        return static::$connection::table('leaf_schema_history', null, $connection);
    }

    /**
     * Get the last applied state for a table from history
     * @param string $tableName The table to look up
     * @param string|null $connection The connection to use
     * @return array|null
     */
    protected static function lastAppliedState(string $tableName, ?string $connection = null): ?array
    {
        static::importStorageSnapshots($tableName, $connection);

        $row = static::history($connection)
            ->where('table_name', $tableName)
            ->orderBy('id', 'desc')
            ->first();

        return $row ? Yaml::parse($row->schema) : null;
    }

    /**
     * Record a newly applied state in history
     * @param string $tableName The table the state belongs to
     * @param array $data The applied state (parsed schema file)
     * @param string|null $connection The connection to use
     * @return void
     */
    protected static function recordState(string $tableName, array $data, ?string $connection = null): void
    {
        static::history($connection)->insert([
            'table_name' => $tableName,
            'schema' => Yaml::dump($data),
            'created_at' => tick()->format('YYYY-MM-DD HH:mm:ss'),
        ]);
    }

    /**
     * One-time import of pre-v5 storage/ snapshots into history, so
     * existing apps upgrade without losing their diff baseline
     * @param string $tableName The table to import snapshots for
     * @param string|null $connection The connection to use
     * @return void
     */
    protected static function importStorageSnapshots(string $tableName, ?string $connection = null): void
    {
        if (!function_exists('StoragePath') || !storage()->exists(StoragePath("database/$tableName"))) {
            return;
        }

        if (static::history($connection)->where('table_name', $tableName)->count() === 0) {
            foreach (glob(StoragePath("database/$tableName/*.yml")) as $snapshot) {
                static::recordState($tableName, Yaml::parseFile($snapshot), $connection);
            }
        }

        // history is the source of truth now
        storage()->delete(StoragePath("database/$tableName"));
    }

    /**
     * Set the internal db connection
     * @param mixed $connection
     * @return void
     */
    public static function setDbConnection($connection)
    {
        static::$connection = $connection;
    }
}
