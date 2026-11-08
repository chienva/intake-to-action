<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

class RunStateReader
{
    public function __construct(private FreshConnection $connections) {}

    public function byId(string $id): ?object
    {
        return $this->connections->open()->table('extraction_runs')->where('id', $id)->first(['id']);
    }

    public function byRequestId(string $requestId): ?object
    {
        return $this->connections->open()->table('extraction_runs')->where('request_id', $requestId)->first(['id']);
    }
}
