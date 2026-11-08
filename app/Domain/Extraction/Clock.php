<?php

declare(strict_types=1);

namespace App\Domain\Extraction;

interface Clock
{
    public function now(): \DateTimeImmutable;
}
