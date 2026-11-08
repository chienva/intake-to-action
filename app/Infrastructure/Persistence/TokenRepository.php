<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class TokenRepository
{
    public function issue(User $user, string $name, int $days): string
    {
        return DB::transaction(fn () => $user->createToken($name, ['extractions:create'], now()->addDays($days))->plainTextToken);
    }

    public function cleanup(string $name): bool
    {
        $connection = app(FreshConnection::class)->open();
        $connection->table('personal_access_tokens')->where('name', $name)->delete();

        return $connection->table('personal_access_tokens')->where('name', $name)->count() === 0;
    }
}
