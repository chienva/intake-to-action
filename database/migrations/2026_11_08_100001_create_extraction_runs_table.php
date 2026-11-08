<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extraction_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->uuid('request_id');
            $table->foreignId('user_id')->nullable()->constrained();
            $table->char('input_sha256', 64);
            $table->integer('input_length');
            $table->string('status');
            $table->string('status_set');
            $table->json('errors');
            $table->json('order')->nullable();
            $table->string('schema_version');
            $table->string('prompt_version');
            $table->string('gateway');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extraction_runs');
    }
};
