<?php

declare(strict_types=1);

namespace App\Domain\Extraction;

interface CatalogueRepository
{
    public function contains(string $sku): bool;
}
