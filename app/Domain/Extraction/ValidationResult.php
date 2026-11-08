<?php

declare(strict_types=1);

namespace App\Domain\Extraction;

final readonly class ValidationResult
{
    public function __construct(public ExtractionStatus $status, public array $errors, public ?array $order = null) {}
}
