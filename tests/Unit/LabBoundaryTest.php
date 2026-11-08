<?php

use App\Support\LabPolicy;
use Illuminate\Filesystem\Filesystem;

require_once dirname(__DIR__).'/LabProcesses.php';

beforeEach(function () {
    $GLOBALS['labDirectories'] = [];
});

afterEach(function () {
    foreach ($GLOBALS['labDirectories'] as $directory) {
        (new Filesystem)->deleteDirectory($directory);
    }
});

test('preflight refuses unsafe process and file configurations', function (array $environment, string $envText, ?string $fixture, string $reason) {
    $directory = labCopy();
    file_put_contents($directory.'/.env', $envText);
    if ($fixture === 'cache') {
        file_put_contents($directory.'/bootstrap/cache/config.php', '<?php return [];');
    }
    if ($fixture === 'symlink') {
        unlink($directory.'/.env');
        symlink($directory.'/.env.example', $directory.'/.env');
    }
    labExpected(labProcess(['php', 'scripts/lab-preflight.php'], $directory, $environment), 1, 'REFUSED: '.$reason, true);
})->with([
    'process production' => [['APP_ENV' => 'production'], '', null, 'environment'],
    'process mysql' => [['DB_CONNECTION' => 'mysql'], '', null, 'driver'],
    'process database URL' => [['DB_URL' => 'sqlite:///tmp/outside.sqlite'], '', null, 'database-url'],
    'process legacy database URL' => [['DATABASE_URL' => 'sqlite:///tmp/outside.sqlite'], '', null, 'database-url'],
    'process outside database' => [['DB_DATABASE' => '/tmp/database.sqlite'], '', null, 'database-path'],
    'cached config' => [[], '', 'cache', 'cached-config'],
    'symlinked env' => [[], '', 'symlink', 'env-file'],
    'file production' => [[], "APP_ENV=production\n", null, 'environment'],
    'last assignment' => [[], "APP_ENV=local\nAPP_ENV=\"production\"\n", null, 'environment'],
    'interpolation' => [[], 'DB_DATABASE=${HOME}/x', null, 'env-file'],
    'quoted mysql' => [[], "DB_CONNECTION='mysql'\n", null, 'driver'],
    'file database URL' => [[], "DATABASE_URL='sqlite:///tmp/outside.sqlite'\n", null, 'database-url'],
    'testing outside database' => [['APP_ENV' => 'testing', 'DB_DATABASE' => '/tmp/database.sqlite'], '', null, 'database-path'],
    'local memory' => [['APP_ENV' => 'local', 'DB_DATABASE' => ':memory:'], '', null, 'database-path'],
]);

test('preflight accepts the example and missing canonical database', function () {
    $directory = labCopy();
    copy($directory.'/.env.example', $directory.'/.env');
    expect(labProcess(['php', 'scripts/lab-preflight.php'], $directory))->toBe([0, '', '']);
    file_put_contents($directory.'/.env', 'APP_ENV=${UNTRUSTED}'."\nAPP_ENV='local' # final assignment wins\nDB_CONNECTION=\"sqlite\"\n");
    expect(labProcess(['php', 'scripts/lab-preflight.php'], $directory))->toBe([0, '', '']);
    expect(labProcess(['php', 'scripts/lab-preflight.php'], $directory, ['DB_DATABASE' => $directory.'/database/database.sqlite']))->toBe([0, '', '']);
    expect(labProcess(['php', 'scripts/lab-preflight.php'], $directory, ['APP_ENV' => 'testing', 'DB_DATABASE' => ':memory:']))->toBe([0, '', '']);
});

test('lab guard and boot provider refuse unsafe runtime configuration', function (string $environment, string $driver, string $path, bool $symlink, string $reason) {
    $directory = labCopy();
    $database = $path === 'canonical' ? $directory.'/database/database.sqlite' : '/tmp/database.sqlite';
    if ($symlink) {
        symlink('/tmp/database.sqlite', $directory.'/database/database.sqlite');
    }
    file_put_contents($directory.'/.env', "APP_ENV={$environment}\nAPP_DEBUG=false\nDB_CONNECTION={$driver}\nDB_DATABASE={$database}\n");
    labExpected(labProcess(['php', 'artisan', 'intake:lab-guard'], $directory), 1, 'REFUSED: '.$reason);
    labExpected(labProcess(['php', 'artisan', 'list'], $directory), 1, 'REFUSED: '.$reason);
})->with([
    'production' => ['production', 'sqlite', 'canonical', false, 'environment'],
    'mysql' => ['local', 'mysql', 'canonical', false, 'driver'],
    'outside' => ['local', 'sqlite', 'outside', false, 'database-path'],
    'symlink' => ['local', 'sqlite', 'canonical', true, 'database-path'],
    'testing mysql' => ['testing', 'mysql', 'canonical', false, 'driver'],
    'testing outside' => ['testing', 'sqlite', 'outside', false, 'database-path'],
    'testing symlink' => ['testing', 'sqlite', 'canonical', true, 'database-path'],
]);

test('lab guard accepts local canonical path before database creation', function () {
    $directory = labCopy();
    expect(labProcess(['php', 'artisan', 'intake:lab-guard'], $directory))->toBe([0, '', '']);
    expect(LabPolicy::validDatabase(':memory:', 'local', $directory))->toBeFalse();
    expect(LabPolicy::validDatabase(':memory:', 'testing', $directory))->toBeTrue();
});

test('HTTP entrypoint refuses production at boot', function () {
    $directory = labCopy();
    file_put_contents($directory.'/.env', "APP_ENV=production\nAPP_DEBUG=false\nDB_CONNECTION=sqlite\n");
    [$code, $stdout, $stderr] = labProcess(['php', 'public/index.php'], $directory, ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/up']);
    expect($code)->toBe(0);
    expect(trim($stdout.$stderr))->toBe('REFUSED: environment');
    // Verify the response status produced by the actual HTTP entrypoint as a process.
    $probe = $directory.'/http-status.php';
    file_put_contents($probe, '<?php register_shutdown_function(function () { fwrite(STDERR, "STATUS=".http_response_code()); }); require __DIR__."/public/index.php";');
    $response = labProcess(['php', $probe], $directory, ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/up']);
    expect($response[0])->toBe(0);
    expect($response[1].$response[2])->toContain('STATUS=503');
});

test('Composer preflight refuses before package discovery and documents the no-scripts boundary', function () {
    $directory = labCopy(true);
    file_put_contents($directory.'/.env', "APP_ENV=production\n");
    $result = labProcess(['composer', 'dump-autoload'], $directory);
    labExpected($result, 1, 'REFUSED: environment');
    expect($result[1].$result[2])->not->toContain('artisan package:discover');
    $bypass = labProcess(['composer', 'dump-autoload', '--no-scripts'], $directory);
    expect($bypass[0])->toBe(0);
    fwrite(STDOUT, "documented Composer boundary: expected=0, observed={$bypass[0]}, PASS\n");
});

test('setup refuses hostile env in a fresh clone before Composer creates vendor', function () {
    $directory = sys_get_temp_dir().'/intake-t2-fresh-'.bin2hex(random_bytes(8));
    $GLOBALS['labDirectories'][] = $directory;
    $root = dirname(__DIR__, 2);
    expect(labProcess(['git', 'clone', '--quiet', '--local', '--no-hardlinks', $root, $directory], $root)[0])->toBe(0);
    $head = trim(labProcess(['git', 'rev-parse', 'HEAD'], $root)[1]);
    expect(trim(labProcess(['git', 'rev-parse', 'HEAD'], $directory)[1]))->toBe($head);
    file_put_contents($directory.'/.env', "APP_ENV=production\n");
    labExpected(labProcess(['bash', 'scripts/codex-setup.sh'], $directory), 1, 'REFUSED: environment', true);
    expect(is_dir($directory.'/vendor'))->toBeFalse();
});

test('codex check reports each base refusal before other checks', function (string $scenario, string $reason) {
    $directory = labCopy();
    $root = dirname(__DIR__, 2);
    // The script must run against a real checkout, preserving this branch's HEAD.
    expect(labProcess(['git', 'clone', '--quiet', '--local', '--no-hardlinks', $root, $directory.'/checkout'], $root)[0])->toBe(0);
    $checkout = $directory.'/checkout';
    $head = trim(labProcess(['git', 'rev-parse', 'HEAD'], $checkout)[1]);
    $environment = ['CODEX_REMOTE' => '/nonexistent', 'EXPECTED_BASE' => $head];
    if ($scenario !== 'missing') {
        $remote = $directory.'/remote.git';
        expect(labProcess(['git', 'init', '--bare', '--quiet', $remote], $root)[0])->toBe(0);
        expect(labProcess(['git', 'push', '--quiet', $remote, 'HEAD:refs/heads/main'], $checkout)[0])->toBe(0);
        $environment['CODEX_REMOTE'] = $remote;
        if ($scenario === 'behind') {
            $tree = trim(labProcess(['git', 'rev-parse', 'HEAD^{tree}'], $checkout)[1]);
            $new = labProcess(['git', '-c', 'user.name=Synthetic Lab', '-c', 'user.email=lab@example.test', 'commit-tree', $tree, '-p', $head, '-m', 'Synthetic unrelated remote advance'], $checkout);
            expect($new[0])->toBe(0);
            expect(labProcess(['git', 'push', '--quiet', $remote, trim($new[1]).':refs/heads/main'], $checkout)[0])->toBe(0);
        } elseif ($scenario === 'mismatch') {
            $environment['EXPECTED_BASE'] = str_repeat('0', 40);
        } else {
            $environment['EXPECTED_BASE'] = false;
        }
    }
    labExpected(labProcess(['bash', 'scripts/codex-check.sh'], $checkout, $environment), 69, 'BLOCKED: '.$reason, true, true);
})->with([
    'missing remote' => ['missing', 'base-unverified'],
    'remote ahead' => ['behind', 'base-behind'],
    'wrong expected base' => ['mismatch', 'base-mismatch'],
    'unset expected base' => ['unspecified', 'base-unspecified'],
]);
