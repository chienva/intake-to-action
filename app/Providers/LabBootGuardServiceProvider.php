<?php

namespace App\Providers;

use App\Support\LabPolicy;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class LabBootGuardServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (($reason = LabPolicy::refusal()) !== null) {
            throw new RuntimeException('REFUSED: '.$reason);
        }
    }
}
