<?php

use App\Domain\Extraction\DuplicateKeyScanner;
use Random\Engine\Mt19937;
use Random\Randomizer;

test('scanner matches independent ground truth for 10000 seeded valid JSON documents', function () {
    $seed = 734219;
    $rng = new Randomizer(new Mt19937($seed));
    // Each pair is a JSON spelling and independently supplied decoded identity.
    $names = [['"a"', 'a'], ['"\\u0061"', 'a'], ['"b"', 'b'], ['"\\u0062"', 'b'], ['""', ''], ['"😀"', '😀'], ['"\\uD83D\\uDE00"', '😀'], ['"\\u007A"', 'z'], ['"0"', '0']];
    $generate = function (int $depth) use (&$generate, $rng, $names): array {
        if ($depth === 15 || $rng->getInt(0, 4) < 2) {
            return [['null', 'true', '17', json_encode('escaped"value', JSON_THROW_ON_ERROR)][$rng->getInt(0, 3)], false, $depth];
        }
        $object = $rng->getInt(0, 1) === 1;
        $parts = [];
        $seen = [];
        $duplicate = false;
        $maxDepth = $depth + 1;
        for ($j = 0, $n = $rng->getInt(0, 4); $j < $n; $j++) {
            [$text, $childDuplicate, $childDepth] = $generate($depth + 1);
            $maxDepth = max($maxDepth, $childDepth);
            $duplicate = $duplicate || $childDuplicate;
            if ($object) {
                [$token, $identity] = $names[$rng->getInt(0, count($names) - 1)];
                $duplicate = $duplicate || in_array($identity, $seen, true);
                $seen[] = $identity;
                $text = $token.':'.$text;
            }
            $parts[] = $text;
        }

        return [($object ? '{' : '[').implode(',', $parts).($object ? '}' : ']'), $duplicate, $maxDepth];
    };
    $scanner = new DuplicateKeyScanner;
    $positive = 0;
    $negative = 0;
    $deepest = 0;
    for ($index = 0; $index < 10000; $index++) {
        [$raw, $truth, $depth] = $generate(0);
        $deepest = max($deepest, $depth);
        $message = 'seed='.$seed.' document='.$index;
        $this->assertTrue(json_validate($raw, 16), $message);
        try {
            json_decode($raw, false, 16, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            $this->fail($message.' json_decode rejected document');
        }
        $this->assertSame($truth, $scanner->scan($raw) !== [], $message);
        if ($truth) {
            $positive++;
        } else {
            $negative++;
        }
    }
    expect($positive)->toBeGreaterThan(0);
    expect($negative)->toBeGreaterThan(0);
    expect($deepest)->toBe(15);
});
