<?php

use Symfony\Component\Yaml\Yaml;
use Illuminate\Database\Capsule\Manager as Capsule;

test('migrate creates a table from a schema file', function () {
    $file = schemaFile('users', <<<'YAML'
columns:
  name: string
  email:
    type: string
    length: 120
    nullable: false
    unique: true
  age:
    type: integer
    nullable: true
  status:
    type: string
    default: active
softDeletes: true
remember_token: true
YAML);

    expect(\Leaf\Schema::migrate($file))->toBeTrue();
    expect(dbSchema()->hasTable('users'))->toBeTrue();

    $columns = columnInfo('users');

    // default id + timestamps, plus softDeletes and remember_token
    expect(array_keys($columns))->toContain(
        'id',
        'name',
        'email',
        'age',
        'status',
        'deleted_at',
        'remember_token',
        'created_at',
        'updated_at'
    );

    expect($columns['name']['type_name'])->toBe('varchar');
    expect($columns['age']['type_name'])->toBe('integer');
    expect($columns['age']['nullable'])->toBeTrue();
    expect($columns['email']['nullable'])->toBeFalse();
    expect($columns['status']['default'])->toContain('active');

    expect(dbSchema()->hasIndex('users', ['email'], 'unique'))->toBeTrue();

    // the applied state is recorded in the history table
    expect(historyRows('users'))->toHaveCount(1);
    expect(Yaml::parse(historyRows('users')[0]->schema))->toBe(Yaml::parseFile($file));
});

test('migrating an edited schema file alters the table', function () {
    $file = schemaFile('users', <<<'YAML'
columns:
  name: string
  email: string
YAML);

    \Leaf\Schema::migrate($file);

    schemaFile('users', <<<'YAML'
columns:
  name:
    type: string
    nullable: true
  bio: text
YAML);

    expect(\Leaf\Schema::migrate($file))->toBeTrue();

    $columns = columnInfo('users');

    expect(array_keys($columns))->toContain('bio'); // added
    expect(array_keys($columns))->not->toContain('email'); // removed
    expect($columns['name']['nullable'])->toBeTrue(); // attribute change applied

    expect(historyRows('users'))->toHaveCount(2);
});

test('tables whose names end in y/m/l are addressed correctly (rtrim regression)', function () {
    $file = schemaFile('company', <<<'YAML'
columns:
  name: string
YAML);

    expect(\Leaf\Schema::migrate($file))->toBeTrue();

    // rtrim($x, '.yml') would have mangled 'company' into 'compan'
    expect(dbSchema()->hasTable('company'))->toBeTrue();
    expect(dbSchema()->hasTable('compan'))->toBeFalse();
    expect(historyRows('company'))->toHaveCount(1);
    expect(historyRows('compan'))->toHaveCount(0);

    // alter path targets the right table too
    schemaFile('company', <<<'YAML'
columns:
  name: string
  nickname: string
YAML);
    \Leaf\Schema::migrate($file);
    expect(dbSchema()->getColumnListing('company'))->toContain('nickname');

    // rollback targets the right table and history rows
    expect(\Leaf\Schema::rollback($file))->toBeTrue();
    expect(dbSchema()->getColumnListing('company'))->not->toContain('nickname');

    // drop targets the right table and history rows
    expect(\Leaf\Schema::drop($file))->toBeTrue();
    expect(dbSchema()->hasTable('company'))->toBeFalse();
    expect(historyRows('company'))->toHaveCount(0);
});

test('rollback restores the previous table state and schema file', function () {
    $v1 = <<<'YAML'
columns:
  name: string
YAML;

    $file = schemaFile('posts', $v1);
    \Leaf\Schema::migrate($file);

    schemaFile('posts', <<<'YAML'
columns:
  name: string
  subtitle: string
YAML);
    \Leaf\Schema::migrate($file);
    expect(dbSchema()->getColumnListing('posts'))->toContain('subtitle');

    expect(\Leaf\Schema::rollback($file))->toBeTrue();

    expect(dbSchema()->getColumnListing('posts'))->not->toContain('subtitle');

    // the schema file is left untouched (it is now ahead of the db);
    // history head is back to v1
    expect(Yaml::parseFile($file))->not->toBe(Yaml::parse($v1));
    expect(historyRows('posts'))->toHaveCount(1);
    expect(Yaml::parse(historyRows('posts')[0]->schema))->toBe(Yaml::parse($v1));

    // re-running migrate re-applies the file's newer state
    expect(\Leaf\Schema::migrate($file))->toBeTrue();
    expect(dbSchema()->getColumnListing('posts'))->toContain('subtitle');
});

test('seeds insert inline data arrays', function () {
    $file = schemaFile('pets', <<<'YAML'
columns:
  name: string
seeds:
  data:
    - name: milo
    - name: luna
YAML);

    \Leaf\Schema::migrate($file);
    expect(\Leaf\Schema::seed($file))->toBeTrue();

    $rows = \Illuminate\Database\Capsule\Manager::table('pets')->get();

    expect($rows)->toHaveCount(2);
    expect($rows->pluck('name')->all())->toBe(['milo', 'luna']);
    expect($rows[0]->created_at)->not->toBeNull(); // timestamps filled in
});

test('seeds generate rows from count with faker, randomString and hash tokens', function () {
    $file = schemaFile('accounts', <<<'YAML'
columns:
  email: string
  token: string
  password: string
seeds:
  count: 3
  data:
    email: '@faker.email'
    token: '@randomString:16'
    password: '@hash:secret'
YAML);

    \Leaf\Schema::migrate($file);
    \Leaf\Schema::seed($file);

    $rows = \Illuminate\Database\Capsule\Manager::table('accounts')->get();

    expect($rows)->toHaveCount(3);

    foreach ($rows as $row) {
        expect($row->email)->toContain('@');
        expect(strlen($row->token))->toBe(16);
        expect($row->password)->not->toBe('secret');
        expect(password_verify('secret', $row->password))->toBeTrue();
    }
});

test('seeding with truncate empties the table first', function () {
    $file = schemaFile('pets', <<<'YAML'
columns:
  name: string
seeds:
  data:
    - name: milo
    - name: luna
YAML);

    \Leaf\Schema::migrate($file);
    \Leaf\Schema::seed($file);
    expect(\Illuminate\Database\Capsule\Manager::table('pets')->count())->toBe(2);

    schemaFile('pets', <<<'YAML'
columns:
  name: string
seeds:
  truncate: true
  data:
    - name: rex
YAML);
    \Leaf\Schema::seed($file);

    $rows = \Illuminate\Database\Capsule\Manager::table('pets')->get();
    expect($rows)->toHaveCount(1);
    expect($rows[0]->name)->toBe('rex');
});

test('seeds can come from a model __seeder', function () {
    $file = schemaFile('pets', <<<'YAML'
columns:
  name: string
seeds:
  model: Pet
  count: 2
YAML);

    \Leaf\Schema::migrate($file);
    expect(\Leaf\Schema::seed($file))->toBeTrue();

    $rows = \Illuminate\Database\Capsule\Manager::table('pets')->get();

    expect($rows)->toHaveCount(2);

    foreach ($rows as $row) {
        expect($row->name)->toStartWith('pet-');
    }
});

test('an existing table with no history is adopted without altering', function () {
    $file = schemaFile('users', <<<'YAML'
columns:
  name: string
YAML);

    \Leaf\Schema::migrate($file);

    Capsule::table('leaf_schema_history')->where('table_name', 'users')->delete();

    schemaFile('users', <<<'YAML'
columns:
  name: string
  nickname: string
YAML);

    // no baseline to diff against: the current file is adopted as the
    // baseline and nothing is altered
    expect(\Leaf\Schema::migrate($file))->toBeTrue();
    expect(dbSchema()->getColumnListing('users'))->not->toContain('nickname');
    expect(historyRows('users'))->toHaveCount(1);
});

test('last snapshot without a columns key does not crash', function () {
    $file = schemaFile('users', <<<'YAML'
columns:
  name: string
YAML);

    \Leaf\Schema::migrate($file);

    // rewrite the latest history row without a `columns` key
    Capsule::table('leaf_schema_history')
        ->where('table_name', 'users')
        ->update(['schema' => "timestamps: true\n"]);

    schemaFile('users', <<<'YAML'
columns:
  name: string
  nickname: string
YAML);

    expect(\Leaf\Schema::migrate($file))->toBeTrue();
    expect(dbSchema()->hasTable('users'))->toBeTrue();
    expect(dbSchema()->getColumnListing('users'))->toContain('nickname');
});

test('drop removes the table and its snapshots', function () {
    $file = schemaFile('users', <<<'YAML'
columns:
  name: string
YAML);

    \Leaf\Schema::migrate($file);
    expect(dbSchema()->hasTable('users'))->toBeTrue();

    expect(\Leaf\Schema::drop($file))->toBeTrue();
    expect(dbSchema()->hasTable('users'))->toBeFalse();
    expect(is_dir(StoragePath('database/users')))->toBeFalse();
});

test('reset recreates the table from scratch', function () {
    $file = schemaFile('pets', <<<'YAML'
columns:
  name: string
seeds:
  data:
    - name: milo
YAML);

    \Leaf\Schema::migrate($file);
    \Leaf\Schema::seed($file);
    expect(\Illuminate\Database\Capsule\Manager::table('pets')->count())->toBe(1);

    expect(\Leaf\Schema::reset($file))->toBeTrue();
    expect(dbSchema()->hasTable('pets'))->toBeTrue();
    expect(\Illuminate\Database\Capsule\Manager::table('pets')->count())->toBe(0);
});

test('pre-v5 storage snapshots are imported into history once', function () {
    $file = schemaFile('users', <<<'YAML'
columns:
  name: string
  nickname: string
YAML);

    // simulate a v4 app: table exists, baseline lives in storage/, no history
    \Leaf\Schema::migrate($file);
    $baseline = Yaml::parseFile($file);
    Capsule::schema()->drop('leaf_schema_history');

    mkdir(StoragePath('database/users'), 0777, true);
    file_put_contents(StoragePath('database/users/2024_01_01_000000.yml'), Yaml::dump($baseline));

    schemaFile('users', <<<'YAML'
columns:
  name: string
YAML);

    // migrate imports the storage snapshot as the diff baseline, so the
    // removed column is dropped; the storage dir is cleaned up after
    expect(\Leaf\Schema::migrate($file))->toBeTrue();
    expect(dbSchema()->getColumnListing('users'))->not->toContain('nickname');
    expect(is_dir(StoragePath('database/users')))->toBeFalse();
    expect(historyRows('users'))->toHaveCount(2);
});

test('modern seed tokens mirror real PHP calls with typed arguments', function () {
    $file = schemaFile('members', <<<'YAML'
columns:
  email: string
  age: integer
  plan: string
  token: string
  password: string
  joined: string
seeds:
  count: 5
  data:
    email: '@faker.unique.safeEmail'
    age: '@faker.numberBetween(18, 65)'
    plan: '@faker.randomElement(["free", "pro", "scale"])'
    token: '@randomString(32)'
    password: '@hash("secret")'
    joined: '@tick.subtract(30, "day").format("YYYY-MM-DD")'
YAML);

    \Leaf\Schema::migrate($file);
    expect(\Leaf\Schema::seed($file))->toBeTrue();

    $rows = Capsule::table('members')->get();
    expect($rows)->toHaveCount(5);

    $emails = [];

    foreach ($rows as $row) {
        expect($row->email)->toContain('@');
        expect($row->age)->toBeGreaterThanOrEqual(18)->toBeLessThanOrEqual(65);
        expect(in_array($row->plan, ['free', 'pro', 'scale']))->toBeTrue();
        expect(strlen($row->token))->toBe(32);
        expect(password_verify('secret', $row->password))->toBeTrue();
        expect($row->joined)->toBe(tick()->subtract(30, 'day')->format('YYYY-MM-DD'));
        $emails[] = $row->email;
    }

    // one faker instance per run makes unique() hold across rows
    expect(array_unique($emails))->toHaveCount(5);
});

test('seed tokens resolve inside inline row arrays and literals pass through', function () {
    $file = schemaFile('members', <<<'YAML'
columns:
  name: string
  email: string
seeds:
  data:
    - name: mychi
      email: '@faker.unique.safeEmail'
    - name: '@not-a-token'
      email: static@leafphp.dev
YAML);

    \Leaf\Schema::migrate($file);
    \Leaf\Schema::seed($file);

    $rows = Capsule::table('members')->orderBy('id')->get();
    expect($rows[0]->email)->toContain('@');
    expect($rows[1]->name)->toBe('@not-a-token');
    expect($rows[1]->email)->toBe('static@leafphp.dev');
});

test('pre-v5 colon seed tokens still work', function () {
    $file = schemaFile('members', <<<'YAML'
columns:
  token: string
  password: string
seeds:
  count: 2
  data:
    token: '@randomString:16'
    password: '@hash:secret'
YAML);

    \Leaf\Schema::migrate($file);
    \Leaf\Schema::seed($file);

    $rows = Capsule::table('members')->get();

    foreach ($rows as $row) {
        expect(strlen($row->token))->toBe(16);
        expect(password_verify('secret', $row->password))->toBeTrue();
    }
});

test('seeds respect a faker locale', function () {
    $file = schemaFile('members', <<<'YAML'
columns:
  name: string
seeds:
  count: 1
  locale: fr_FR
  data:
    name: '@faker.name'
YAML);

    \Leaf\Schema::migrate($file);
    expect(\Leaf\Schema::seed($file))->toBeTrue();
    expect(Capsule::table('members')->count())->toBe(1);
});

test('a malformed seed token throws a helpful error', function () {
    $file = schemaFile('members', <<<'YAML'
columns:
  age: integer
seeds:
  count: 1
  data:
    age: '@faker.numberBetween(18, 65'
YAML);

    \Leaf\Schema::migrate($file);
    expect(fn () => \Leaf\Schema::seed($file))
        ->toThrow(Exception::class, 'Could not parse seed token');
});

test('a schema with no seeds block seeds nothing', function () {
    // this is the shipped password_resets.yml shape — before the guard,
    // seeding guessed App\Models\PasswordReset from the table name and
    // threw on every fresh `db:migrate --seed`
    $file = schemaFile('password_resets', <<<'YAML'
increments: false
timestamps: false

columns:
  email:
    type: string
  token: string
YAML);

    \Leaf\Schema::migrate($file);

    expect(\Leaf\Schema::seed($file))->toBeTrue();
    expect(\Illuminate\Database\Capsule\Manager::table('password_resets')->get())->toHaveCount(0);
});

test('seeds with count zero seed nothing even with a model', function () {
    $file = schemaFile('logs', <<<'YAML'
columns:
  message: string
seeds:
  count: 0
  model: MissingModel
YAML);

    \Leaf\Schema::migrate($file);

    // the model doesn't exist, but with nothing to seed that must not matter
    expect(\Leaf\Schema::seed($file))->toBeTrue();
    expect(\Illuminate\Database\Capsule\Manager::table('logs')->get())->toHaveCount(0);
});
