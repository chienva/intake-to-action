<?php

require_once __DIR__.'/../LabProcesses.php';

test('real timeout environment reaches config and boot with the approved R3a result', function () {
    $root = labCopy();
    $environment = ['INTAKE_GATEWAY' => 'prism', 'INTAKE_PROVIDER' => 'openai', 'INTAKE_MODEL' => 'test-model'];
    [$exit, $stdout, $stderr] = labProcess([PHP_BINARY, 'artisan', 'list'], $root, [...$environment, 'INTAKE_TIMEOUT' => '20']);
    fwrite(STDOUT, 'timeout boot expected=0 observed='.$exit.' '.($exit === 0 ? 'PASS' : 'FAIL')."\n");
    expect($exit)->toBe(0);
    expect($stderr)->toBe('');
    expect($stdout)->toContain('Available commands:');

    [$exit, $stdout, $stderr] = labProcess([PHP_BINARY, 'artisan', 'list'], $root, [...$environment, 'INTAKE_TIMEOUT' => 'abc']);
    $pass = $exit === 1 && $stdout === '' && $stderr === "intake.extraction.timeout_seconds\n";
    fwrite(STDOUT, 'timeout boot expected=1 observed='.$exit.' '.($pass ? 'PASS' : 'FAIL')." stderr=intake.extraction.timeout_seconds\\n stdout=empty\n");
    expect($exit)->toBe(1);
    expect($stdout)->toBe('');
    expect($stderr)->toBe("intake.extraction.timeout_seconds\n");
});
