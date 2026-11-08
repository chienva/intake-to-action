<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class FreshConnection
{
    public function open(): Connection
    {
        $name = 'intake_fresh';
        config(['database.connections.'.$name => DB::connection()->getConfig()]);
        DB::purge($name);

        return DB::connection($name);
    }
}
