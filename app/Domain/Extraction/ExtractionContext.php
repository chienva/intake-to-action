<?php

declare(strict_types=1);

namespace App\Domain\Extraction;

final readonly class ExtractionContext
{
    public function __construct(public string $requestId, public ?int $userId) {}
}
