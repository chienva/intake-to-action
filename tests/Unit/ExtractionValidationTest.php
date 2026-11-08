<?php

use App\Domain\Extraction\BusinessRules;
use App\Domain\Extraction\ContainerCheck;
use App\Domain\Extraction\ContainerResult;
use App\Domain\Extraction\DuplicateKeyScanner;
use App\Domain\Extraction\ExtractionStatus;
use App\Domain\Extraction\FixtureDateResolver;
use App\Domain\Extraction\FrozenClock;
use App\Domain\Extraction\GatewayFailure;
use App\Domain\Extraction\GatewayIntegrity;
use App\Domain\Extraction\GatewayResult;
use App\Domain\Extraction\InMemoryCatalogueRepository;
use App\Domain\Extraction\ShapeValidator;
use App\Domain\Extraction\StatusResolver;
use Tests\ExtractionCases as Cases;

test('fixture hash and expected validation', function (string $name, array $fixture) {
    expect(hash_file('sha256', __DIR__.'/../../resources/fixtures/orders/'.$name))->toBe($fixture['sha256']);
    $raw = (new FixtureDateResolver(Cases::clock()))->resolve($fixture['raw']);
    $result = Cases::result($raw);
    expect($result->status->value)->toBe($fixture['status']);
    expect(array_map(fn ($e) => $e->jsonSerialize(), $result->errors))->toBe($fixture['errors']);
    expect((new DuplicateKeyScanner)->scan($raw))->toBe([]);
})->with(function () {
    foreach (Cases::fixtures() as $name => $fixture) {
        yield $name => [$name, $fixture];
    }
});

test('integrity is checked before decoding', function (GatewayIntegrity $integrity, string $code) {
    $result = Cases::result('invalid JSON', $integrity);
    expect($result->status)->toBe(ExtractionStatus::RejectedByValidation);
    expect(array_map(fn ($e) => $e->jsonSerialize(), $result->errors))->toBe([['code' => $code, 'path' => '$']]);
})->with([
    [GatewayIntegrity::TextMissing, 'raw_text_unavailable'],
    [GatewayIntegrity::Refusal, 'model_refusal'],
    [GatewayIntegrity::StructuredMismatch, 'raw_structured_mismatch'],
]);

test('each contract error code rejects', function (string $code, string $raw) {
    $result = Cases::result($raw);
    expect($result->status)->toBe(ExtractionStatus::RejectedByValidation);
    expect(array_column(array_map(fn ($e) => $e->jsonSerialize(), $result->errors), 'code'))->toContain($code);
})->with(function () {
    $p = json_decode(Cases::raw(), true, flags: JSON_THROW_ON_ERROR);
    yield 'schema_unparseable' => ['schema_unparseable', '{'];
    yield 'key_duplicate' => ['key_duplicate', '{"outcome":"extracted",'.substr(Cases::raw(), 1)];
    foreach ([
        'key_missing' => function (&$p) {
            unset($p['outcome']);
        },
        'key_unexpected' => function (&$p) {
            $p['extra'] = true;
        },
        'type_invalid' => function (&$p) {
            $p['customer_code'] = 1;
        },
        'enum_invalid' => function (&$p) {
            $p['currency'] = 'EUR';
        },
        'string_too_long' => function (&$p) {
            $p['customer_code'] = str_repeat('a', 17);
        },
        'line_items_too_many' => function (&$p) {
            $p['line_items'] = array_fill(0, 51, $p['line_items'][0]);
        },
        'reject_reason_missing' => function (&$p) {
            $p['outcome'] = 'rejected';
        },
        'reject_payload_inconsistent' => function (&$p) {
            $p['outcome'] = 'rejected';
            $p['reject_reason'] = 'other';
        },
        'reject_reason_unexpected' => function (&$p) {
            $p['reject_reason'] = 'other';
        },
        'field_required' => function (&$p) {
            $p['customer_code'] = null;
        },
        'line_items_empty' => function (&$p) {
            $p['line_items'] = [];
        },
        'customer_code_invalid' => function (&$p) {
            $p['customer_code'] = 'X-001';
        },
        'date_invalid' => function (&$p) {
            $p['requested_delivery_date'] = '2026-02-30';
        },
        'date_in_past' => function (&$p) {
            $p['requested_delivery_date'] = '2026-11-07';
        },
        'date_too_far' => function (&$p) {
            $p['requested_delivery_date'] = '2027-11-09';
        },
        'sku_unknown' => function (&$p) {
            $p['line_items'][0]['sku'] = 'ZZZ-9999';
        },
        'quantity_out_of_range' => function (&$p) {
            $p['line_items'][0]['quantity'] = 0;
        },
        'unit_invalid' => function (&$p) {
            $p['line_items'][0]['unit'] = 'each';
        },
    ] as $code => $mutate) {
        $copy = $p;
        $mutate($copy);
        yield $code => [$code, Cases::encode($copy)];
    }
});

test('container rejects non-string and excessive depth', function () {
    $container = new ContainerCheck;
    expect($container->validate(null)->errors[0]->code)->toBe('schema_unparseable');
    expect($container->validate(str_repeat('[', 17).'0'.str_repeat(']', 17))->errors[0]->code)->toBe('schema_unparseable');
});

test('date boundaries use local date and fixed 365 days', function (string $today, string $date, ?string $error) {
    $p = json_decode(Cases::raw(), true, flags: JSON_THROW_ON_ERROR);
    $p['requested_delivery_date'] = $date;
    $result = Cases::resolver(Cases::clock($today))->resolve(new GatewayResult(Cases::encode($p), GatewayIntegrity::Ok));
    expect(array_map(fn ($e) => $e->code, $result->errors))->toBe($error === null ? [] : [$error]);
    expect($result->status)->toBe($error === null ? ExtractionStatus::AcceptedForReview : ExtractionStatus::RejectedByValidation);
})->with([
    ['2026-11-08', '2026-11-08', null],
    ['2026-11-08', '2026-11-07', 'date_in_past'],
    ['2026-11-08', '2027-11-08', null],
    ['2026-11-08', '2027-11-09', 'date_too_far'],
    ['2026-11-08', '2026-02-30', 'date_invalid'],
    ['2026-11-08', "2026-12-01\n", 'string_too_long'],
    ['2027-03-01', '2028-02-29', null],
    ['2027-03-01', '2028-03-01', 'date_too_far'],
]);

test('clock converts UTC instant across the local midnight boundary', function () {
    $clock = new FrozenClock(new DateTimeImmutable('2026-11-07T18:00:00+00:00'));
    $p = json_decode(Cases::raw(), true, flags: JSON_THROW_ON_ERROR);
    $p['requested_delivery_date'] = '2026-11-07';
    $result = Cases::resolver($clock)->resolve(new GatewayResult(Cases::encode($p), GatewayIntegrity::Ok));
    expect($result->errors[0]->code)->toBe('date_in_past');
    expect((new FixtureDateResolver($clock))->resolve('"+0d"'))->toBe('"2026-11-08"');
});

test('provider failure and bypassed unknown outcome fail closed', function () {
    expect(Cases::resolver()->resolve(new GatewayFailure('SyntheticFailure'))->status)->toBe(ExtractionStatus::FailedProvider);
    $container = new class extends ContainerCheck
    {
        public function validate(mixed $raw, GatewayIntegrity $integrity = GatewayIntegrity::Ok): ContainerResult
        {
            return new ContainerResult(['outcome' => 'unexpected'], []);
        }
    };
    $shape = new class extends ShapeValidator
    {
        public function validate(array $payload): array
        {
            return [];
        }
    };
    $resolver = new StatusResolver(new BusinessRules(Cases::clock(), new InMemoryCatalogueRepository([])), $container, $shape);
    expect($resolver->resolve(new GatewayResult('{}', GatewayIntegrity::Ok))->status)->toBe(ExtractionStatus::RejectedByValidation);
});

test('stage ordering and path ordering are deterministic and contain no input keys', function () {
    $p = json_decode(Cases::raw(), true, flags: JSON_THROW_ON_ERROR);
    $p['SENTINEL_KEY_7f3a'] = true;
    $p['customer_code'] = 1;
    $p['line_items'][0]['SENTINEL_KEY_7f3a'] = 'private';
    unset($p['line_items'][0]['sku']);
    $result = Cases::result(Cases::encode($p));
    expect(array_map(fn ($e) => $e->jsonSerialize(), $result->errors))->toBe([
        ['code' => 'key_unexpected', 'path' => '$'],
        ['code' => 'type_invalid', 'path' => '$.customer_code'],
        ['code' => 'key_unexpected', 'path' => '$.line_items.0'],
        ['code' => 'key_missing', 'path' => '$.line_items.0.sku'],
    ]);
    $p = json_decode(Cases::raw(), true, flags: JSON_THROW_ON_ERROR);
    $p['customer_code'] = 'bad';
    $p['currency'] = null;
    $p['reject_reason'] = 'other';
    $p['line_items'][0]['unit'] = 'each';
    expect(array_map(fn ($e) => $e->code, Cases::result(Cases::encode($p))->errors))->toBe(['field_required', 'reject_reason_unexpected', 'customer_code_invalid', 'unit_invalid']);
});

test('duplicate paths stay at nearest contract object', function (string $raw, string $path) {
    $result = (new ContainerCheck)->validate($raw);
    expect(array_map(fn ($e) => $e->jsonSerialize(), $result->errors))->toBe([['code' => 'key_duplicate', 'path' => $path]]);
})->with([
    ['{"extra":{"deep":{"a":1,"a":2}}}', '$'],
    ['{"line_items":[{"extra":{"a":1,"a":2}}]}', '$.line_items.0'],
    ['{"line_items":[{"sku":"a","sk\\u0075":"b"}]}', '$.line_items.0'],
    ['{"😀":1,"\\uD83D\\uDE00":2}', '$'],
]);

test('raw mutation matrix rejects every mutant and preserves valid replacements twice', function () {
    $scanner = new DuplicateKeyScanner;
    $runs = [];
    $counts = array_fill_keys(range('a', 'g'), 0);
    $preserved = 0;
    $cases = Cases::mutations();
    for ($pass = 0; $pass < 2; $pass++) {
        $summary = [];
        foreach ($cases as $label => [$group, $raw, $preserve, $original]) {
            $result = Cases::result($raw);
            expect($result->status->value, $label)->toBe($preserve ? $original : 'rejected_by_validation');
            foreach ($result->errors as $error) {
                expect(str_contains($error->path, 'SENTINEL_KEY_7f3a'), $label)->toBeFalse();
            }
            $jsonValid = json_validate($raw, 16);
            if ($jsonValid) {
                expect($scanner->scan($raw) !== [], $label)->toBe($group === 'f');
            } else {
                expect($result->errors[0]->code, $label)->toBe('schema_unparseable');
            }
            $summary[$label] = [$result->status->value, array_map(fn ($e) => $e->jsonSerialize(), $result->errors)];
            if ($pass === 0) {
                if ($preserve) {
                    $preserved++;
                } else {
                    $counts[$group]++;
                }
            }
        }
        $runs[] = $summary;
    }
    expect($runs[0] === $runs[1])->toBeTrue();
    expect($counts)->toBe(['a' => 486, 'b' => 36, 'c' => 162, 'd' => 12, 'e' => 15, 'f' => 42, 'g' => 24]);
    expect($preserved)->toBe(12);
});
