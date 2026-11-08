<?php

declare(strict_types=1);

namespace App\Domain\Extraction;

final readonly class ValidationError implements \JsonSerializable
{
    public function __construct(public string $code, public string $path) {}

    public function jsonSerialize(): array
    {
        return ['code' => $this->code, 'path' => $this->path];
    }

    public static function sorted(array $errors): array
    {
        usort($errors, fn (self $a, self $b) => [$a->path, $a->code] <=> [$b->path, $b->code]);

        return $errors;
    }
}
