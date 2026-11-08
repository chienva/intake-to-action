<?php

declare(strict_types=1);

namespace App\Domain\Extraction;

final class StorageFailure extends \RuntimeException
{
    public function __construct(public readonly string $causeClass)
    {
        parent::__construct('');
    }
}
