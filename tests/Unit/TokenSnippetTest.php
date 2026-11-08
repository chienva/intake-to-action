<?php

test('the sole AGENTS token function matches the snippet byte for byte', function () {
    $root = dirname(__DIR__, 2);
    $agents = file_get_contents($root.'/AGENTS.md');
    expect(substr_count($agents, 'intake_token()'))->toBe(1);
    expect(preg_match('/intake_token\(\)[^\r\n]*?\}/', $agents, $match))->toBe(1);
    expect($match[0])->toBe(rtrim(file_get_contents($root.'/docs/snippets/intake_token.sh'), "\n"));
});
