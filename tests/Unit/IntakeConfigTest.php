<?php

use App\Domain\Extraction\InvalidIntakeConfiguration;
use App\Support\IntakeConfigValidator;
use Illuminate\Support\Env;

test('timeout environment normalization preserves strict validation', function (?string $value, mixed $normalized, bool $valid) {
    $repository = Env::getRepository();
    $previous = $repository->get('INTAKE_TIMEOUT');
    if ($value === null) {
        $repository->clear('INTAKE_TIMEOUT');
    } else {
        $repository->set('INTAKE_TIMEOUT', $value);
    }
    try {
        $config = require __DIR__.'/../../config/intake.php';
        expect($config['extraction']['timeout_seconds'])->toBe($normalized);
        $config['extraction'] = array_replace($config['extraction'], ['gateway' => 'prism', 'provider' => 'openai', 'model' => 'test-model']);
        $validator = new IntakeConfigValidator;
        if ($valid) {
            $validator->validate($config['extraction'], 'local');
            expect(true)->toBeTrue();
        } else {
            try {
                $validator->validate($config['extraction'], 'local');
                test()->fail('Expected configuration rejection');
            } catch (InvalidIntakeConfiguration $exception) {
                expect($exception->getMessage())->toBe('intake.extraction.timeout_seconds');
            }
        }
    } finally {
        if ($previous === null) {
            $repository->clear('INTAKE_TIMEOUT');
        } else {
            $repository->set('INTAKE_TIMEOUT', $previous);
        }
    }
})->with([
    [null, 20, true], ['20', 20, true], ['1', 1, true], ['120', 120, true],
    ['0', 0, false], ['121', 121, false], ['abc', 'abc', false],
    ['20.5', '20.5', false], [' 20', ' 20', false], ['-5', '-5', false], ['', '', false],
]);

test('intake config fails closed with exactly the config key', function (array $overrides, string $environment, string $key) {
    $base = ['gateway' => 'prism', 'provider' => 'openai', 'model' => 'synthetic', 'timeout_seconds' => 20, 'provider_options' => []];
    expect(fn () => (new IntakeConfigValidator)->validate(array_replace($base, $overrides), $environment))
        ->toThrow(InvalidIntakeConfiguration::class, 'intake.extraction.'.$key);
    try {
        (new IntakeConfigValidator)->validate(array_replace($base, $overrides), $environment);
        test()->fail('Expected configuration rejection');
    } catch (InvalidIntakeConfiguration $exception) {
        expect($exception->getMessage())->toBe('intake.extraction.'.$key);
    }
})->with([
    [['gateway' => 'bogus'], 'local', 'gateway'],
    [['provider' => ''], 'local', 'provider'],
    [['model' => ''], 'local', 'model'],
    [['timeout_seconds' => 0], 'local', 'timeout_seconds'],
    [['timeout_seconds' => 121], 'local', 'timeout_seconds'],
    [['timeout_seconds' => '20'], 'local', 'timeout_seconds'],
    [['timeout_seconds' => 20.0], 'local', 'timeout_seconds'],
    [['provider_options' => ['use_tool_calling' => true]], 'local', 'provider_options.use_tool_calling'],
    [['provider_options' => ['use_tool_calling' => 'yes']], 'local', 'provider_options.use_tool_calling'],
    [['gateway' => 'fake'], 'production', 'gateway'],
]);

test('valid fake and prism configuration pass including timeout limits', function () {
    $validator = new IntakeConfigValidator;
    $validator->validate(['gateway' => 'fake'], 'local');
    foreach ([1, 120] as $timeout) {
        $validator->validate(['gateway' => 'prism', 'provider' => 'openai', 'model' => 'synthetic', 'timeout_seconds' => $timeout, 'provider_options' => ['use_tool_calling' => false]], 'local');
    }
    expect(true)->toBeTrue();
});
