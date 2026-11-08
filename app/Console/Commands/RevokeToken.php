<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

class RevokeToken extends Command
{
    protected $signature = 'intake:revoke-token {id?} {--all-for=} {--name=}';

    protected $description = 'Revoke tokens by id, owner or issuance name';

    public function handle(): int
    {
        try {
            $query = PersonalAccessToken::query();
            $id = $this->argument('id');
            $email = $this->option('all-for');
            $name = $this->option('name');
            if (count(array_filter([$id, $email, $name], fn ($value) => $value !== null)) !== 1) {
                throw new \InvalidArgumentException;
            }
            if ($name !== null) {
                if (! preg_match('/\Aintake-[0-9a-f]{16}\z/', $name)) {
                    throw new \InvalidArgumentException;
                }
                $query->where('name', $name);
            } elseif ($id !== null) {
                $query->whereKey($id);
            } else {
                $user = User::where('email', $email)->first();
                $query->where('tokenable_type', (new User)->getMorphClass())->where('tokenable_id', $user?->id ?? -1);
            }
            $deleted = (clone $query)->delete();
            if ($query->count() !== 0) {
                throw new \RuntimeException;
            }
        } catch (\Throwable) {
            $this->output->getErrorStyle()->writeln('FAILED: revoke');

            return 1;
        }
        $this->line('revoked '.$deleted);

        return 0;
    }
}
