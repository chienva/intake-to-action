<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Persistence\TokenRepository;
use App\Models\User;
use Illuminate\Console\Command;

class IssueToken extends Command
{
    protected $signature = 'intake:issue-token {email} {--ttl-days=1}';

    protected $description = 'Issue one expiring operator token';

    public function handle(TokenRepository $tokens): int
    {
        $ttl = $this->option('ttl-days');
        if (! is_string($ttl) || ! preg_match('/\A[1-7]\z/', $ttl)) {
            $this->output->getErrorStyle()->writeln('FAILED: not-issued');

            return 1;
        }
        $name = 'intake-'.bin2hex(random_bytes(8));
        try {
            $user = User::where('email', $this->argument('email'))->firstOrFail();
            $plaintext = $tokens->issue($user, $name, (int) $ttl);
        } catch (\Throwable) {
            try {
                $clean = $tokens->cleanup($name);
            } catch (\Throwable) {
                $clean = false;
            }
            $this->output->getErrorStyle()->writeln($clean ? 'FAILED: not-issued' : 'FAILED: cleanup '.$name);

            return $clean ? 1 : 2;
        }
        $this->output->writeln($plaintext);

        return 0;
    }
}
