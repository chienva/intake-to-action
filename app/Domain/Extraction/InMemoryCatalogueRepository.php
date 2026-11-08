<?php

declare(strict_types=1);

namespace App\Domain\Extraction;

final readonly class InMemoryCatalogueRepository implements CatalogueRepository
{
    public function __construct(private array $skus) {}

    public function contains(string $sku): bool
    {
        return in_array($sku, $this->skus, true);
    }
}
