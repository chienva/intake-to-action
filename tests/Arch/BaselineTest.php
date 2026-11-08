<?php

arch('App does not contain debugging helpers')
    ->expect('App')
    ->not->toUse(['dd', 'dump', 'var_dump', 'ray']);

test('Composer has no audit or policy overrides', function () {
    $composer = json_decode(file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    expect($composer['config'])->not->toHaveKeys(['audit', 'policy']);
});
