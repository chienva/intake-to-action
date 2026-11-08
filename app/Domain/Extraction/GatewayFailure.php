<?php

declare(strict_types=1);

namespace App\Domain\Extraction;

final readonly class GatewayFailure
{
    public function __construct(public string $exceptionClass) {}
}
