<?php

namespace App\Console\Commands;

use App\Infrastructure\Persistence\RunStateReader;
use Illuminate\Console\Command;

class RunState extends Command
{
    protected $signature = 'intake:run-state {request_id}';

    protected $description = 'Reconcile a request against committed storage';

    public function handle(RunStateReader $runs): int
    {
        $row = $runs->byRequestId($this->argument('request_id'));
        $this->line($row === null ? 'absent' : 'committed '.$row->id);

        return 0;
    }
}
