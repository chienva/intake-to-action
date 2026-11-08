<?php

namespace App\Models;

use App\Domain\Extraction\ExtractionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ExtractionRun extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $attributes = ['status_set' => ExtractionStatus::STATUS_SET];

    protected function casts(): array
    {
        return ['status' => ExtractionStatus::class, 'errors' => 'array', 'order' => 'array'];
    }

    public function save(array $options = [])
    {
        if ($this->exists) {
            throw new \LogicException('Extraction runs are immutable');
        }

        return parent::save($options);
    }

    public function update(array $attributes = [], array $options = [])
    {
        throw new \LogicException('Extraction runs are immutable');
    }

    public function newEloquentBuilder($query)
    {
        return new ImmutableExtractionRunBuilder($query);
    }

    protected function incrementOrDecrement($column, $amount, $extra, $method)
    {
        throw new \LogicException('Extraction runs are immutable');
    }
}
