<?php

namespace Database\Seeders;

use App\Models\CatalogueItem;
use Illuminate\Database\Seeder;

class CatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $items = [];
        for ($i = 1; $i <= 20; $i++) {
            $items[] = ['sku' => sprintf('ABC-%04d', $i), 'name' => 'Invented workshop supply '.$i, 'unit' => ['pcs', 'box', 'kg'][($i - 1) % 3]];
        }
        CatalogueItem::upsert($items, ['sku'], ['name', 'unit']);
    }
}
