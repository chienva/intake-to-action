<?php

declare(strict_types=1);

namespace App\Domain\Extraction;

final readonly class FixtureDateResolver
{
    public function __construct(private Clock $clock) {}

    public function resolve(string $raw): string
    {
        $today = $this->clock->now()->setTimezone(new \DateTimeZone('Asia/Ho_Chi_Minh'))->setTime(0, 0);

        return preg_replace_callback('/"([+-][0-9]+)d"/', fn (array $m) => '"'.$today->modify($m[1].' days')->format('Y-m-d').'"', $raw);
    }
}
