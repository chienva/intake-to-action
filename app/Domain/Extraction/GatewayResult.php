<?php

declare(strict_types=1);

namespace App\Domain\Extraction;

final readonly class GatewayResult
{
    public function __construct(public string $rawJson, public GatewayIntegrity $integrity, public ?int $inputTokens = null, public ?int $outputTokens = null) {}
}
