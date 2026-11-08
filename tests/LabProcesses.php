<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

function labProcess(array $command, string $cwd, array $environment = []): array
{
    $environment = array_merge(array_fill_keys([
        'APP_ENV', 'DB_CONNECTION', 'DB_DATABASE', 'DB_URL', 'DATABASE_URL',
        'APP_CONFIG_CACHE', 'APP_EVENTS_CACHE', 'APP_ROUTES_CACHE', 'EXPECTED_BASE', 'CODEX_REMOTE',
    ], false), $environment);
    // Read $? as the next shell command, without disabling errexit or swallowing status.
    $process = new Process(['bash', '-c', '"$@"; observed=$?; exit "$observed"', 'lab-process', ...$command], $cwd, $environment);
    $process->setTimeout(120);
    $process->run();

    return [$process->getExitCode(), $process->getOutput().$process->getErrorOutput()];
}

function labCopy(bool $vendor = false): string
{
    $root = dirname(__DIR__);
    $temp = sys_get_temp_dir().'/intake-t2-test-'.bin2hex(random_bytes(8));
    mkdir($temp);
    $files = new Filesystem;
    foreach (['app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'scripts', 'storage'] as $directory) {
        $files->copyDirectory($root.'/'.$directory, $temp.'/'.$directory);
    }
    foreach (glob($temp.'/bootstrap/cache/*.php') as $file) {
        unlink($file);
    }
    foreach (glob($temp.'/database/*.sqlite*') as $file) {
        unlink($file);
    }
    foreach (['artisan', 'composer.json', 'composer.lock', '.env.example'] as $file) {
        copy($root.'/'.$file, $temp.'/'.$file);
    }
    if ($vendor) {
        $files->copyDirectory($root.'/vendor', $temp.'/vendor');
    } else {
        symlink($root.'/vendor', $temp.'/vendor');
    }
    file_put_contents($temp.'/.env', "APP_ENV=local\nAPP_DEBUG=false\nDB_CONNECTION=sqlite\n");
    test()->labDirectories[] = $temp;

    return $temp;
}

function labExpected(array $result, int $expected, string $message, bool $exact = false): void
{
    [$observed, $output] = $result;
    $matches = $observed === $expected && ($exact ? trim($output) === $message : str_contains($output, $message));
    fwrite(STDOUT, "expected={$expected}, observed={$observed}, ".($matches ? 'PASS' : 'FAIL')." [{$message}]\n");
    expect($observed)->toBe($expected);
    if ($exact) {
        expect(trim($output))->toBe($message);
    } else {
        expect($output)->toContain($message);
    }
}
