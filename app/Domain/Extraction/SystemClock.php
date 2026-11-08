<?php

declare(strict_types=1);

namespace App\Domain\Extraction;

final class SystemClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('Asia/Ho_Chi_Minh'));
    }
}
