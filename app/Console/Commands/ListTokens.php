<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ListTokens extends Command
{
    protected $signature = 'intake:list-tokens {email}';

    protected $description = 'List token metadata only';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        $rows = $user?->tokens()->orderBy('id')->get(['id', 'abilities', 'expires_at', 'last_used_at']) ?? [];
        foreach ($rows as $row) {
            $this->line(json_encode($row->only(['id', 'abilities', 'expires_at', 'last_used_at']), JSON_THROW_ON_ERROR));
        }

        return 0;
    }
}
