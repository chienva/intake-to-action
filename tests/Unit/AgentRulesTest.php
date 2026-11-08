<?php

test('AGENTS is the sole durable agent instruction file', function () {
    $root = dirname(__DIR__, 2);
    expect(file_exists($root.'/CLAUDE.md'))->toBeFalse();
    $agents = file_get_contents($root.'/AGENTS.md');
    expect($agents)->not->toMatch('/tonytomalex\/t\d/');
    expect($agents)->not->toMatch('/\b[0-9a-f]{40}\b/');
});
