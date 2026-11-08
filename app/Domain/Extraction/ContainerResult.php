<?php

declare(strict_types=1);

namespace App\Domain\Extraction;

final readonly class ContainerResult
{
    public function __construct(public ?array $payload, public array $errors) {}
}
