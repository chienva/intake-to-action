<?php

use Tests\WritePathScanner;

test('production extraction writes have one owner', function () {
    $violations = (new WritePathScanner)->violations(dirname(__DIR__, 2));
    expect($violations)->toBe([], implode(', ', $violations));
});

test('write scanner detects query model alias instance and raw SQL writes', function () {
    $scanner = new WritePathScanner;
    foreach ([
        'DB::table("extraction_runs")->insert([]);',
        'DB::table("extraction_runs")->update([]);',
        '$connection->table("extraction_runs")->delete();',
        'ExtractionRun::create([]);',
        'ExtractionRun::whereKey(1)->update([]);',
        'use App\\Models\\ExtractionRun as Run; Run::create([]);',
        '$row = new ExtractionRun; $row->save();',
        '$row = new \\App\\Models\\ExtractionRun; $row->save();',
        '$row = app(ExtractionRun::class); $row->save();',
        'DB::statement("INSERT INTO extraction_runs VALUES (1)");',
        'DB::statement("DELETE FROM extraction_runs");',
    ] as $source) {
        expect($scanner->unsafe($source))->toBeTrue();
    }
    expect($scanner->unsafe('DB::table("extraction_runs")->insert([]);', true))->toBeFalse();
    expect($scanner->unsafe('DB::table("extraction_runs")->update([]);', true))->toBeTrue();
});

test('production code never builds ad hoc log channels', function () {
    $root = dirname(__DIR__, 2);
    $violations = [];
    foreach (['app', 'bootstrap', 'config', 'routes'] as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() === 'php' && preg_match('/(?:Log\s*::\s*build\s*\(|logger\s*\(\s*\)\s*->\s*build\s*\()/i', file_get_contents($file->getPathname()))) {
                $violations[] = $file->getPathname();
            }
        }
    }
    expect($violations)->toBe([]);
});
