<?php

use App\Domain\Extraction\InvalidIntakeConfiguration;
use App\Support\IntakeConfigValidator;

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
