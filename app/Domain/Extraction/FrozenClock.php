<?php

declare(strict_types=1);

namespace App\Domain\Extraction;

final readonly class FrozenClock implements Clock
{
    public function __construct(private \DateTimeImmutable $instant) {}

    public function now(): \DateTimeImmutable
    {
        return $this->instant;
    }
}
