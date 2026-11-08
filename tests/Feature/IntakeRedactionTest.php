<?php

use App\Console\Commands\ListTokens;
use App\Domain\Extraction\ExtractionGateway;
use App\Domain\Extraction\OrderExtractionContract;
use App\Infrastructure\Persistence\RunRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPUnit\Framework\AssertionFailedError;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\PrismManager;
use Prism\Prism\Structured\Request;
use Prism\Prism\Structured\Response;
use Prism\Prism\Testing\PrismFake;
use Tests\ExtractionCases;
use Tests\RedactionNets;
use Tests\SqliteColumnScanner;
use Tests\T4GatewayDouble;
use Tests\T4Harness;

class MarkerException extends RuntimeException {}

function t4SentinelPatterns(string $sentinel): void
{
    foreach ([OrderExtractionContract::CUSTOMER_PATTERN, OrderExtractionContract::DATE_PATTERN, OrderExtractionContract::SKU_PATTERN] as $pattern) {
        expect(preg_match('/'.$pattern.'/', $sentinel))->toBe(0);
    }
    foreach ([['extracted', 'rejected'], OrderExtractionContract::REASONS, OrderExtractionContract::CURRENCIES, ['pcs', 'box', 'kg']] as $allowed) {
        expect(in_array($sentinel, $allowed, true))->toBeFalse();
    }
    expect(is_numeric($sentinel))->toBeFalse();
}

beforeEach(function () {
    $this->h = new T4Harness($this);
    $this->nets = new RedactionNets($this->h);
});

afterEach(function () {
    $this->nets->restore();
});

test('redaction controls independently observe every configured channel and each escape route', function () {
    $this->nets->activateErrorLog();
    foreach ($this->nets->names as $name) {
        $marker = 'control-channel-'.hash('sha256', $name);
        Log::channel($name)->warning($marker);
        expect(str_contains(implode('', $this->nets->channels[$name] ?? []), $marker))->toBeTrue('net 2 channel '.$name);
        expect(str_contains(implode('', $this->nets->events), $marker))->toBeTrue();
    }
    $m2 = 'control-runtime-'.Str::uuid();
    $runtimeFile = storage_path('logs/t4-runtime.log');
    Log::build(['driver' => 'single', 'path' => $runtimeFile])->warning($m2);
    expect(str_contains(implode('', $this->nets->events), $m2))->toBeTrue();
    expect(str_contains(file_get_contents($runtimeFile), $m2))->toBeTrue();
    $m3 = 'control-emergency-'.Str::uuid();
    config(['logging.channels.emergency' => ['path' => storage_path('logs/laravel.log')], 'logging.default' => 't4_invalid', 'logging.channels.t4_invalid' => ['driver' => 'invalid-driver']]);
    Log::forgetChannel('t4_invalid');
    Log::warning($m3);
    expect(str_contains(implode('', $this->nets->events), $m3))->toBeTrue();
    expect(str_contains(file_get_contents(storage_path('logs/laravel.log')), $m3))->toBeTrue();
    config(['logging.default' => 'stack']);
    $m4 = 'control-errorlog-'.Str::uuid();
    expect(ini_get('error_log'))->toBe($this->nets->errorFile);
    expect(error_log($m4))->toBeTrue();
    expect(str_contains(file_get_contents($this->nets->errorFile), $m4))->toBeTrue();
    $m5 = 'control-stdout-'.Str::uuid();
    $m6 = 'control-stderr-'.Str::uuid();
    file_put_contents($this->h->root.'/routes/console.php', "\n\\Illuminate\\Support\\Facades\\Artisan::command('t4:control', function () { fwrite(STDOUT, ".var_export($m5, true).'); fwrite(STDERR, '.var_export($m6, true)."); });\n", FILE_APPEND);
    [$exit, $stdout, $stderr] = $this->h->process([PHP_BINARY, 'artisan', 't4:control']);
    expect($exit)->toBe(0);
    expect($stdout)->toBe($m5);
    expect($stderr)->toBe($m6);
    $m7 = 'control-exception-message-'.Str::uuid();
    report(new MarkerException($m7));
    $events = implode('', $this->nets->events);
    expect(str_contains($events, MarkerException::class))->toBeTrue();
    expect(str_contains($events, $m7))->toBeFalse();
    $exceptionRecords = array_values(array_filter($this->nets->events, fn ($record) => str_contains($record, MarkerException::class)));
    expect($exceptionRecords)->toHaveCount(1);
    $record = unserialize($exceptionRecords[0]);
    expect($record[0])->toBe('exception');
    expect(array_keys($record[1]))->toBe(['exception_class', 'request_id', 'file', 'line']);
    expect($record[1]['exception_class'])->toBe(MarkerException::class);
    $this->nets->assertAbsent($m7);
});

test('column scanner covers real tables every storage type and detects its own missing table mutant', function () {
    $this->nets->activateErrorLog();
    $scanner = new SqliteColumnScanner;
    $scanner->assertCoverage(DB::connection()->getPdo(), $scanner->scan(DB::connection()->getPdo()));
    $pdo = new PDO('sqlite::memory:');
    $expected = [];
    foreach (['one', 'two', 'three'] as $table) {
        $pdo->exec('CREATE TABLE "'.$table.'" (t TEXT, i INTEGER, b BLOB, j JSON)');
        $markers = [];
        foreach (['t', 'i', 'b', 'j'] as $column) {
            $marker = 'scanner-'.$table.'-'.$column;
            $expected[$table.'.'.$column] = [$marker];
            $markers[] = $marker;
        }
        $statement = $pdo->prepare('INSERT INTO "'.$table.'" VALUES (?, ?, ?, ?)');
        $statement->execute($markers);
    }
    ksort($expected);
    expect($scanner->scan($pdo))->toBe($expected);
    $scanner->assertCoverage($pdo, $scanner->scan($pdo));
    $mutant = new class extends SqliteColumnScanner
    {
        public function scan(PDO $pdo): array
        {
            return array_filter(parent::scan($pdo), fn ($key) => ! str_starts_with($key, 'two.'), ARRAY_FILTER_USE_KEY);
        }
    };
    expect(fn () => $mutant->assertCoverage($pdo, $mutant->scan($pdo)))->toThrow(AssertionFailedError::class);
});

test('independent sentinels cannot escape HTTP persistence logs or process output', function (string $point, bool $debug) {
    $this->nets->activateErrorLog();
    config(['app.debug' => $debug]);
    $sentinel = 'SENTINEL_redaction_'.Str::uuid().'!';
    t4SentinelPatterns($sentinel);
    $token = $this->h->token();
    switch ($point) {
        case 'document':
            $response = $this->h->post($token, $sentinel)->assertStatus(201);
            break;
        case 'model-value':
        case 'model-key':
            $this->h->prism();
            $payload = json_decode(ExtractionCases::raw(), true);
            if ($point === 'model-value') {
                $payload['customer_code'] = $sentinel;
            } else {
                $payload[$sentinel] = 1;
            }
            $raw = json_encode($payload, JSON_THROW_ON_ERROR);
            Prism::fake([t4Response($raw, $payload)]);
            $response = $this->h->post($token)->assertStatus(201)->assertJsonPath('status', 'rejected_by_validation');
            break;
        case 'authorization':
            $response = $this->h->post($sentinel)->assertStatus(401);
            break;
        case 'provider-message':
            $this->h->prism();
            $fake = new class($sentinel) extends PrismFake
            {
                public function __construct(private string $sentinel) {}

                public function structured(Request $request): Response
                {
                    throw new RuntimeException($this->sentinel);
                }
            };
            // Bind the public provider manager boundary, without modifying fake internals.
            app()->instance(PrismManager::class, new class($fake) extends PrismManager
            {
                public function __construct(private PrismFake $fake) {}

                public function resolve(Provider|string $name, array $providerConfig = []): PrismFake
                {
                    return $this->fake;
                }
            });
            $response = $this->h->post($token)->assertStatus(502);
            break;
        case 'exception-before':
            app()->instance(ExtractionGateway::class, new T4GatewayDouble(fn () => throw new RuntimeException($sentinel)));
            $response = $this->h->post($token)->assertStatus(500);
            break;
        case 'exception-after':
            app()->instance(RunRepository::class, new class($sentinel) extends RunRepository
            {
                public function __construct(private string $sentinel) {}

                public function insert(array $attributes): void
                {
                    parent::insert($attributes);
                    throw new RuntimeException($this->sentinel);
                }
            });
            $response = $this->h->post($token)->assertStatus(500)->assertJsonStructure(['run_id']);
            break;
        case 'request-key':
            $response = $this->h->request(json_encode(['document_text' => 'synthetic', $sentinel => 1]), $token)->assertStatus(422);
            expect($response->json('fields'))->toBe([['name' => '$', 'rule' => 'prohibited_key']]);
            break;
        case 'issued-token':
            $permitted = count($this->h->processes);
            [$exit, $stdout, $stderr] = $this->h->process([PHP_BINARY, 'artisan', 'intake:issue-token', 'owner@example.test', '--ttl-days=1']);
            expect($exit)->toBe(0);
            expect(preg_match('/\A[0-9]+\|[A-Za-z0-9]+\n\z/', $stdout) === 1)->toBeTrue();
            expect($stderr)->toBe('');
            $sentinel = trim($stdout);
            t4SentinelPatterns($sentinel);
            $this->h->command(ListTokens::class, ['email' => 'owner@example.test']);
            $response = $this->h->post($sentinel)->assertStatus(201);
            break;
    }
    $this->nets->assertAbsent($sentinel, $permitted ?? null);
})->with([
    ['document', false], ['model-value', false], ['model-key', false], ['authorization', false], ['provider-message', false],
    ['exception-before', false], ['exception-after', false], ['request-key', false], ['issued-token', false],
    ['exception-before', true], ['exception-after', true],
]);
