<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Extraction\StorageFailure;
use Illuminate\Support\Facades\DB;

class RunRepository
{
    public function insert(array $attributes): void
    {
        try {
            DB::transaction(static function () use ($attributes): void {
                DB::table('extraction_runs')->insert($attributes);
            });
        } catch (\Throwable $exception) {
            throw new StorageFailure($exception::class);
        }
    }
}
