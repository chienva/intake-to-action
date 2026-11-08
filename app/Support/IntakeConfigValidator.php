<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Extraction\InvalidIntakeConfiguration;

final class IntakeConfigValidator
{
    public function validate(array $config, string $environment): void
    {
        $fail = static fn (string $key) => throw new InvalidIntakeConfiguration('intake.extraction.'.$key);
        $gateway = $config['gateway'] ?? null;
        if (! in_array($gateway, ['fake', 'prism'], true) || ($environment === 'production' && $gateway === 'fake')) {
            $fail('gateway');
        }
        if ($gateway === 'prism') {
            foreach (['provider', 'model'] as $key) {
                if (! is_string($config[$key] ?? null) || trim($config[$key]) === '') {
                    $fail($key);
                }
            }
            $timeout = $config['timeout_seconds'] ?? null;
            if (! is_int($timeout) || $timeout < 1 || $timeout > 120) {
                $fail('timeout_seconds');
            }
            if (! is_array($config['provider_options'] ?? [])) {
                $fail('provider_options');
            }
            if ($config['provider_options']['use_tool_calling'] ?? false) {
                $fail('provider_options.use_tool_calling');
            }
        }
    }
}
