<?php

return [
    'extraction' => [
        'gateway' => env('INTAKE_GATEWAY', 'fake'),
        'provider' => env('INTAKE_PROVIDER'),
        'model' => env('INTAKE_MODEL'),
        'timeout_seconds' => env('INTAKE_TIMEOUT', 20),
    ],
];
