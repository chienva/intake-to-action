<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class LabSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogueSeeder::class);
    }
}
