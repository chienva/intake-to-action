<?php

declare(strict_types=1);

namespace App\Infrastructure\Catalogue;

use App\Domain\Extraction\CatalogueRepository;
use App\Models\CatalogueItem;

final class EloquentCatalogueRepository implements CatalogueRepository
{
    public function contains(string $sku): bool
    {
        return CatalogueItem::query()->where('sku', $sku)->exists();
    }
}
