<?php

$timeout = env('INTAKE_TIMEOUT', 20);

return [
    'extraction' => [
        'gateway' => env('INTAKE_GATEWAY', 'fake'),
        'provider' => env('INTAKE_PROVIDER'),
        'model' => env('INTAKE_MODEL'),
        'timeout_seconds' => is_string($timeout) && preg_match('/\A[0-9]+\z/', $timeout) === 1 ? (int) $timeout : $timeout,
        'provider_options' => [],
    ],
];
