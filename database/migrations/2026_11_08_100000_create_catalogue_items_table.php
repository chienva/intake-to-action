<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogue_items', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 16)->unique();
            $table->string('name');
            $table->enum('unit', ['pcs', 'box', 'kg']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogue_items');
    }
};
