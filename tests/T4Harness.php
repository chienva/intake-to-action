<?php

namespace Tests;

use App\Domain\Extraction\Clock;
use App\Domain\Extraction\ExtractionContext;
use App\Domain\Extraction\ExtractionGateway;
use App\Domain\Extraction\GatewayFailure;
use App\Domain\Extraction\GatewayResult;
use App\Models\User;
use Database\Seeders\LabSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__.'/LabProcesses.php';

class T4Harness
{
    public string $root;

    public array $processes = [];

    public array $responses = [];

    public function __construct(public TestCase $test)
    {
        $this->root = labCopy();
        touch($this->root.'/database/database.sqlite');
        config(['database.connections.sqlite.database' => $this->root.'/database/database.sqlite']);
        DB::purge();
        DB::purge('intake_fresh');
        $test->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $test->seed(LabSeeder::class);
        app()->instance(Clock::class, ExtractionCases::clock());
    }

    public function token(array $abilities = ['extractions:create'], mixed $expires = null): string
    {
        return User::where('email', 'owner@example.test')->firstOrFail()->createToken('synthetic-test', $abilities, $expires ?? now()->addDay())->plainTextToken;
    }

    public function request(string $raw, ?string $token = null, array $headers = [])
    {
        $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json', 'X-Request-Id' => (string) Str::uuid(), ...$headers];
        if ($token !== null) {
            $headers['Authorization'] = 'Bearer '.$token;
            $id = explode('|', $token)[0];
            RateLimiter::clear('intake:token:'.$id);
        }
        $server = [];
        foreach ($headers as $key => $value) {
            $server[$key === 'Content-Type' ? 'CONTENT_TYPE' : 'HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
        }
        $response = $this->test->call('POST', '/api/v1/extractions', [], [], [], $server, $raw);
        $this->responses[] = $response;

        return $response;
    }

    public function post(?string $token = null, string $text = 'synthetic input', array $headers = [])
    {
        return $this->request(json_encode(['document_text' => $text], JSON_THROW_ON_ERROR), $token, $headers);
    }

    public function prism(): void
    {
        config(['intake.extraction.gateway' => 'prism', 'intake.extraction.provider' => 'openai', 'intake.extraction.model' => 'synthetic-model', 'intake.extraction.timeout_seconds' => 17]);
    }

    public function command(string $class, array $input): array
    {
        $command = app($class);
        $command->setLaravel(app());
        $tester = new CommandTester($command);
        $exit = $tester->execute($input, ['capture_stderr_separately' => true]);
        $result = [$exit, $tester->getDisplay(), $tester->getErrorOutput()];
        $this->processes[] = $result;

        return $result;
    }

    public function process(array $command, array $environment = []): array
    {
        $result = labProcess($command, $this->root, ['APP_ENV' => 'testing', 'DB_DATABASE' => $this->root.'/database/database.sqlite', ...$environment]);
        $this->processes[] = $result;

        return $result;
    }

    public function reconcile(string $requestId, ?string $runId): void
    {
        [$exit, $stdout, $stderr] = $this->process([PHP_BINARY, 'artisan', 'intake:run-state', $requestId]);
        expect($exit)->toBe(0);
        expect($stdout)->toBe($runId === null ? "absent\n" : "committed {$runId}\n");
        expect($stderr)->toBe('');
    }
}

class T4GatewayDouble implements ExtractionGateway
{
    public function __construct(private \Closure $callback) {}

    public function extract(string $text, ExtractionContext $context): GatewayResult|GatewayFailure
    {
        expect(DB::transactionLevel())->toBe(0);

        return ($this->callback)($text, $context);
    }
}

class T4FaultPDO extends \PDO
{
    public function __construct(string $path, public string $mode)
    {
        parent::__construct('sqlite:'.$path);
        $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function commit(): bool
    {
        if ($this->mode === 'durable') {
            parent::commit();
        }
        throw new \RuntimeException('synthetic commit fault');
    }
}
