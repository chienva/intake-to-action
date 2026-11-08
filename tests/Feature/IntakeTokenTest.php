<?php

use App\Console\Commands\IssueToken;
use App\Console\Commands\ListTokens;
use App\Console\Commands\RevokeToken;
use App\Infrastructure\Persistence\FreshConnection;
use App\Infrastructure\Persistence\TokenRepository;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\T4FaultPDO;
use Tests\T4Harness;

beforeEach(function () {
    $this->h = new T4Harness($this);
});

test('issued token has one permitted stdout line and supports expiry ability and revocation', function () {
    [$exit, $stdout, $stderr] = $this->h->command(IssueToken::class, ['email' => 'owner@example.test']);
    expect($exit)->toBe(0);
    // Assert booleans so a failing assertion can never print a credential.
    expect(preg_match('/\A[0-9]+\|[A-Za-z0-9]+\n\z/', $stdout) === 1)->toBeTrue();
    expect($stderr)->toBe('');
    $token = trim($stdout);
    $row = PersonalAccessToken::findToken($token);
    expect($row->name)->toMatch('/\Aintake-[a-f0-9]{16}\z/');
    expect($row->abilities)->toBe(['extractions:create']);
    expect($row->expires_at->between(now()->addHours(23), now()->addHours(25)))->toBeTrue();
    expect(str_contains($row->token, $token))->toBeFalse();
    expect(hash_equals(hash('sha256', explode('|', $token)[1]), $row->token))->toBeTrue();
    $this->h->post($token)->assertStatus(201);
    [$code, $listing, $errors] = $this->h->command(ListTokens::class, ['email' => 'owner@example.test']);
    expect($code)->toBe(0);
    expect($errors)->toBe('');
    expect(str_contains($listing, $token))->toBeFalse();
    expect(str_contains($listing, $row->token))->toBeFalse();
    expect(array_keys(json_decode(trim($listing), true)))->toBe(['id', 'abilities', 'expires_at', 'last_used_at']);
    [$code, $output] = $this->h->command(RevokeToken::class, ['id' => $row->id]);
    expect($code)->toBe(0);
    expect($output)->toBe("revoked 1\n");
    $this->h->post($token)->assertStatus(401);
    [$code, $issued] = $this->h->command(IssueToken::class, ['email' => 'owner@example.test', '--ttl-days' => '7']);
    expect($code)->toBe(0);
    $other = trim($issued);
    [$code] = $this->h->command(RevokeToken::class, ['--all-for' => 'owner@example.test']);
    expect($code)->toBe(0);
    $this->h->post($other)->assertStatus(401);
    $this->h->post($this->h->token(expires: now()->subDay()))->assertStatus(401);
    $this->h->post($this->h->token(['different']))->assertStatus(403);
});

test('invalid token lifetimes create no rows', function (string $ttl) {
    [$exit, $stdout, $stderr] = $this->h->command(IssueToken::class, ['email' => 'owner@example.test', '--ttl-days' => $ttl]);
    expect($exit)->toBe(1);
    expect($stdout)->toBe('');
    expect($stderr)->toBe("FAILED: not-issued\n");
    expect(PersonalAccessToken::count())->toBe(0);
})->with(['0', '8', 'x']);

test('token insert and durable commit faults never publish plaintext and clean durable rows', function (string $fault) {
    if ($fault === 'insert') {
        DB::unprepared("CREATE TRIGGER t4_token_fail BEFORE INSERT ON personal_access_tokens BEGIN SELECT RAISE(ABORT, 'synthetic'); END");
    } else {
        DB::connection()->setPdo(new T4FaultPDO($this->h->root.'/database/database.sqlite', 'durable'));
    }
    [$exit, $stdout, $stderr] = $this->h->command(IssueToken::class, ['email' => 'owner@example.test']);
    expect($exit)->toBe(1);
    expect($stdout)->toBe('');
    expect($stderr)->toBe("FAILED: not-issued\n");
    DB::purge();
    expect(PersonalAccessToken::count())->toBe(0);
})->with(['insert', 'durable']);

test('token cleanup failures expose only issuance name and name revocation is idempotent', function (string $fault) {
    app()->instance(TokenRepository::class, new class($fault) extends TokenRepository
    {
        public function __construct(private string $fault) {}

        public function issue(User $user, string $name, int $days): string
        {
            parent::issue($user, $name, $days);
            throw new RuntimeException('synthetic durable failure');
        }

        public function cleanup(string $name): bool
        {
            $connection = app(FreshConnection::class)->open();
            if ($this->fault === 'delete') {
                $connection->unprepared("CREATE TRIGGER t4_cleanup_fail BEFORE DELETE ON personal_access_tokens BEGIN SELECT RAISE(ABORT, 'synthetic'); END");
            } else {
                $connection->beforeExecuting(function (string $query) {
                    if (str_starts_with($query, 'select count')) {
                        throw new RuntimeException('synthetic count failure');
                    }
                });
                app()->instance(FreshConnection::class, new class($connection) extends FreshConnection
                {
                    public function __construct(private Connection $connection) {}

                    public function open(): Connection
                    {
                        return $this->connection;
                    }
                });
            }

            return parent::cleanup($name);
        }
    });
    [$exit, $stdout, $stderr] = $this->h->command(IssueToken::class, ['email' => 'owner@example.test']);
    expect($exit)->toBe(2);
    expect($stdout)->toBe('');
    expect(preg_match('/\AFAILED: cleanup (intake-[a-f0-9]{16})\n\z/', $stderr, $matches))->toBe(1);
    if ($fault === 'delete') {
        DB::unprepared('DROP TRIGGER t4_cleanup_fail');
    }
    foreach ([1, 2] as $attempt) {
        [$code, $output, $errors] = $this->h->command(RevokeToken::class, ['--name' => $matches[1]]);
        expect($code)->toBe(0);
        expect($output)->toBe('revoked '.($attempt === 1 && $fault === 'delete' ? 1 : 0)."\n");
        expect($errors)->toBe('');
        expect(PersonalAccessToken::where('name', $matches[1])->count())->toBe(0);
    }
})->with(['delete', 'count']);

test('capture pattern process prints only a fixed success or failure marker', function () {
    $pattern = file_get_contents(base_path('docs/snippets/intake_token.sh'))."\nintake_token || exit 1\n";
    [$exit, $stdout, $stderr] = $this->h->process(['bash', '-c', $pattern]);
    expect($exit)->toBe(0);
    expect($stdout)->toBe("token-set\n");
    expect($stderr)->toBe('');
    DB::unprepared("CREATE TRIGGER t4_token_fail BEFORE INSERT ON personal_access_tokens BEGIN SELECT RAISE(ABORT, 'synthetic'); END");
    [$exit, $stdout, $stderr] = $this->h->process(['bash', '-c', $pattern]);
    expect($exit)->toBe(1);
    expect($stdout)->toBe('');
    expect($stderr)->toBe("FAILED: not-issued\ntoken-FAILED rc=1\n");
});

test('invalid gateway refuses process boot with the exact approved R3a diagnostic', function () {
    [$exit, $stdout, $stderr] = $this->h->process([PHP_BINARY, 'artisan', 'list'], ['INTAKE_GATEWAY' => 'bogus']);
    $pass = $exit === 1 && $stdout === '' && $stderr === "intake.extraction.gateway\n";
    fwrite(STDOUT, 'config boot expected=1 observed='.$exit.' '.($pass ? 'PASS' : 'FAIL')." stderr=intake.extraction.gateway\\n\n");
    expect($exit)->toBe(1);
    expect($stdout)->toBe('');
    expect($stderr)->toBe("intake.extraction.gateway\n");
});
