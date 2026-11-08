<?php

use Illuminate\Filesystem\Filesystem;
use Tests\T4Harness;

test('write path R3a probe fails on the temporary file and passes after removal', function () {
    $h = new T4Harness($this);
    $files = new Filesystem;
    $files->copyDirectory(base_path('tests'), $h->root.'/tests');
    copy(base_path('phpunit.xml'), $h->root.'/phpunit.xml');
    $probe = $h->root.'/app/T4WriteProbe.php';
    file_put_contents($probe, '<?php \\Illuminate\\Support\\Facades\\DB::table("extraction_runs")->update(["status" => "synthetic"]);');
    try {
        [$exit, $stdout, $stderr] = $h->process([PHP_BINARY, 'artisan', 'test', '--filter=production extraction writes have one owner']);
        $pass = $exit === 1 && str_contains($stdout.$stderr, 'app/T4WriteProbe.php');
        fwrite(STDOUT, 'write probe expected=1 observed='.$exit.' '.($pass ? 'PASS' : 'FAIL')." file=app/T4WriteProbe.php\n");
        expect($exit)->toBe(1);
        expect(str_contains($stdout.$stderr, 'app/T4WriteProbe.php'))->toBeTrue();
    } finally {
        unlink($probe);
    }
    [$exit] = $h->process([PHP_BINARY, 'artisan', 'test', '--filter=production extraction writes have one owner']);
    expect($exit)->toBe(0);
});
