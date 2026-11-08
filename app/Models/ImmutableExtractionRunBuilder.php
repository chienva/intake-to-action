<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/** Query-level writes must obey the same insert-only rule as model saves. */
class ImmutableExtractionRunBuilder extends Builder
{
    public function update(array $values)
    {
        throw new \LogicException('Extraction runs are immutable');
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        throw new \LogicException('Extraction runs are immutable');
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        throw new \LogicException('Extraction runs are immutable');
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        throw new \LogicException('Extraction runs are immutable');
    }
}
