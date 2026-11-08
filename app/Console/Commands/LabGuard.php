<?php

namespace App\Console\Commands;

use App\Support\LabPolicy;
use Illuminate\Console\Command;

class LabGuard extends Command
{
    protected $signature = 'intake:lab-guard';

    protected $description = 'Verify the synthetic local SQLite lab boundary';

    public function handle(): int
    {
        if (($reason = LabPolicy::refusal()) !== null) {
            $this->line('REFUSED: '.$reason);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
