<?php

use App\Domain\Extraction\ExtractionContext;
use App\Domain\Extraction\ExtractionGateway;
use App\Domain\Extraction\FixtureDateResolver;
use App\Domain\Extraction\GatewayFailure;
use App\Domain\Extraction\GatewayIntegrity;
use App\Domain\Extraction\GatewayResult;
use App\Infrastructure\Persistence\FreshConnection;
use App\Infrastructure\Persistence\RunRepository;
use App\Infrastructure\Persistence\RunStateReader;
use App\Infrastructure\Prism\PrismExtractionGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Structured\Response;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\ValueObjects\Usage;
use Tests\ExtractionCases;
use Tests\T4FaultPDO;
use Tests\T4GatewayDouble;
use Tests\T4Harness;

function t4Response(string $raw, ?array $structured, FinishReason $reason = FinishReason::Stop): Response
{
    $fake = StructuredResponseFake::make();

    return new Response($fake->steps, $raw, $structured, $reason, $fake->usage, $fake->meta);
}

beforeEach(function () {
    $this->h = new T4Harness($this);
});

test('processed Prism and fixture gateway results are committed before responding', function () {
    $this->h->prism();
    $fixtures = ExtractionCases::fixtures();
    foreach (['valid_01.txt', 'not_an_order_01.txt', 'defect_unknown_sku_01.txt'] as $name) {
        $raw = (new FixtureDateResolver(ExtractionCases::clock()))->resolve($fixtures[$name]['raw']);
        Prism::fake([StructuredResponseFake::make()->withText($raw)->withStructured(json_decode($raw, true))]);
        $response = $this->h->post($this->h->token())->assertStatus(201)->assertJsonPath('status', $fixtures[$name]['status']);
        expect(array_keys($response->json()))->toBe(['run_id', 'request_id', 'status', 'status_set', 'order', 'errors', 'schema_version', 'prompt_version']);
        expect(DB::table('extraction_runs')->where('id', $response->json('run_id'))->value('request_id'))->toBe($response->json('request_id'));
        expect($response->json('order') !== null)->toBe($name === 'valid_01.txt');
        $this->h->reconcile($response->json('request_id'), $response->json('run_id'));
    }
    config(['intake.extraction.gateway' => 'fake']);
    foreach (['valid_01.txt' => 'accepted_for_review', 'unknown' => 'rejected_by_model'] as $name => $status) {
        $text = $name === 'unknown' ? 'unrecognized synthetic text' : file_get_contents(resource_path('fixtures/orders/'.$name));
        $this->h->post($this->h->token(), $text)->assertStatus(201)->assertJsonPath('status', $status);
    }
});

test('Prism integrity precedence and strict comparison preserve unmodified text', function () {
    $this->h->prism();
    $raw = ExtractionCases::raw();
    $cases = [
        ['', [], FinishReason::Stop, 'raw_text_unavailable', GatewayIntegrity::TextMissing],
        [" \t\n", [], FinishReason::Stop, 'raw_text_unavailable', GatewayIntegrity::TextMissing],
        ['', [], FinishReason::Refusal, 'model_refusal', GatewayIntegrity::Refusal],
        [$raw, null, FinishReason::Stop, 'raw_structured_mismatch', GatewayIntegrity::StructuredMismatch],
        [$raw, [], FinishReason::Stop, 'raw_structured_mismatch', GatewayIntegrity::StructuredMismatch],
        ['null', null, FinishReason::Stop, 'raw_structured_mismatch', GatewayIntegrity::StructuredMismatch],
        ['[]', [], FinishReason::Stop, 'schema_unparseable', GatewayIntegrity::Ok],
        ['{}', [], FinishReason::Stop, null, GatewayIntegrity::Ok],
        ['{"0":{},"1":[]}', [[], []], FinishReason::Stop, null, GatewayIntegrity::Ok],
        ['{"0":{},"1":[]}', ['different'], FinishReason::Stop, 'raw_structured_mismatch', GatewayIntegrity::StructuredMismatch],
        ['{"nested":{"empty":{},"array":[]}}', ['nested' => ['empty' => [], 'array' => []]], FinishReason::Stop, null, GatewayIntegrity::Ok],
        ['{"nested":{"empty":{},"array":[]}}', ['nested' => []], FinishReason::Stop, 'raw_structured_mismatch', GatewayIntegrity::StructuredMismatch],
    ];
    foreach ($cases as [$text, $structured, $reason, $code, $integrity]) {
        Prism::fake([t4Response($text, $structured, $reason)]);
        $result = app(PrismExtractionGateway::class)->extract('synthetic', new ExtractionContext((string) Str::uuid(), null));
        expect($result->rawJson)->toBe($text);
        expect($result->integrity)->toBe($integrity);
        Prism::fake([t4Response($text, $structured, $reason)]);
        $response = $this->h->post($this->h->token())->assertStatus(201)->assertJsonPath('status', 'rejected_by_validation');
        if ($code !== null) {
            expect($response->json('errors'))->toBe([['code' => $code, 'path' => '$']]);
        }
    }
});

test('all T3 boundary mutants traverse the full HTTP Prism gateway', function () {
    $this->h->prism();
    $invalid = [];
    foreach (ExtractionCases::mutations() as $identifier => [$group, $raw]) {
        if (! in_array($group, ['b', 'c', 'd', 'f', 'g'], true)) {
            continue;
        }
        $structured = json_decode($raw, true);
        $malformed = json_last_error() !== JSON_ERROR_NONE;
        if ($malformed) {
            $invalid[] = $identifier;
            expect($structured)->toBeNull();
        } else {
            expect($structured)->toBeArray();
        }
        Prism::fake([t4Response($raw, $structured)]);
        $response = $this->h->post($this->h->token())->assertStatus(201)->assertJsonPath('status', 'rejected_by_validation');
        if ($malformed) {
            expect($response->json('errors'))->toBe([['code' => 'raw_structured_mismatch', 'path' => '$']]);
        }
        foreach ($response->json('errors') as $error) {
            expect($error['path'])->not->toContain('SENTINEL_KEY_7f3a');
        }
    }
    $expected = [];
    foreach (range(1, 6) as $i) {
        foreach (['customer_code', 'sku', 'requested_delivery_date'] as $field) {
            $expected[] = sprintf('valid_%02d.txt:c:%s-8', $i, $field);
        }
    }
    expect($invalid)->toBe($expected)->toHaveCount(18);
});

test('Prism failures become 502 while an escaping gateway exception becomes 500', function () {
    $this->h->prism();
    $fake = Prism::fake([t4Response(ExtractionCases::raw(), json_decode(ExtractionCases::raw(), true))]);
    // Exhaust the installed fake using its documented sequencing behavior.
    app(PrismExtractionGateway::class)->extract('synthetic', new ExtractionContext((string) Str::uuid(), null));
    $response = $this->h->post($this->h->token())->assertStatus(502);
    expect($response->json())->toBe(['request_id' => $response->json('request_id'), 'error' => 'provider_unavailable', 'run_id' => $response->json('run_id')]);
    expect(DB::table('extraction_runs')->where('id', $response->json('run_id'))->value('status'))->toBe('failed_provider');
    $this->h->reconcile($response->json('request_id'), $response->json('run_id'));
    app()->instance(ExtractionGateway::class, new T4GatewayDouble(fn () => throw new RuntimeException('synthetic')));
    $response = $this->h->post($this->h->token())->assertStatus(500);
    expect($response->json())->toBe(['request_id' => $response->json('request_id'), 'error' => 'server_error']);
    $this->h->reconcile($response->json('request_id'), null);
});

test('storage failures reconcile real insert commit and durable commit outcomes', function (string $fault, bool $providerFailure, int $status, bool $committed, bool $unknown) {
    app()->instance(ExtractionGateway::class, new T4GatewayDouble(fn () => $providerFailure ? new GatewayFailure(RuntimeException::class) : new GatewayResult(ExtractionCases::raw(), GatewayIntegrity::Ok)));
    if ($fault === 'insert') {
        DB::unprepared("CREATE TRIGGER t4_fail BEFORE INSERT ON extraction_runs BEGIN SELECT RAISE(ABORT, 'synthetic insert fault'); END");
    }
    if (in_array($fault, ['commit', 'durable'], true)) {
        DB::connection()->setPdo(new T4FaultPDO($this->h->root.'/database/database.sqlite', $fault));
    }
    if ($fault === 'after') {
        app()->instance(RunRepository::class, new class extends RunRepository
        {
            public function insert(array $attributes): void
            {
                parent::insert($attributes);
                throw new RuntimeException('synthetic after commit');
            }
        });
    }
    if ($fault === 'repository-before') {
        app()->instance(RunRepository::class, new class extends RunRepository
        {
            public function insert(array $attributes): void
            {
                throw new RuntimeException('synthetic pre-insert fault');
            }
        });
    }
    if ($unknown) {
        app()->instance(RunStateReader::class, new class(app(FreshConnection::class)) extends RunStateReader
        {
            public function byId(string $id): ?object
            {
                throw new RuntimeException('synthetic query fault');
            }
        });
    }
    $response = $this->h->post($this->h->token())->assertStatus($status);
    DB::purge();
    $row = DB::table('extraction_runs')->where('request_id', $response->json('request_id'))->first();
    expect($row !== null)->toBe($committed);
    $body = ['request_id' => $response->json('request_id'), 'error' => $status === 503 ? 'storage_unavailable' : 'server_error'];
    if ($unknown) {
        $body['run_state'] = 'unknown';
    } elseif ($committed) {
        $body['run_id'] = $row->id;
    }
    expect($response->json())->toBe($body);
    $this->h->reconcile($response->json('request_id'), $row?->id);
})->with([
    'processed insert' => ['insert', false, 503, false, false],
    'provider insert' => ['insert', true, 503, false, false],
    'commit rollback' => ['commit', false, 503, false, false],
    'after commit' => ['after', false, 500, true, false],
    'after commit unknown' => ['after', false, 500, true, true],
    'durable commit throws' => ['durable', false, 500, true, false],
    'storage query unknown' => ['insert', false, 500, false, true],
    'nonstorage pending failure' => ['repository-before', false, 500, false, false],
]);

test('authentication ability and per token rate limiting use contract bodies', function () {
    $responses = [$this->h->post()->assertStatus(401), $this->h->post('invalid-synthetic')->assertStatus(401)];
    $expired = $this->h->token(expires: now()->subDay());
    $responses[] = $this->h->post($expired)->assertStatus(401);
    $revoked = $this->h->token();
    PersonalAccessToken::findToken($revoked)->delete();
    $responses[] = $this->h->post($revoked)->assertStatus(401);
    foreach ($responses as $response) {
        expect($response->json())->toBe(['request_id' => $response->json('request_id'), 'error' => 'unauthenticated']);
        expect($response->headers->get('X-Request-Id'))->toBe($response->json('request_id'));
    }
    $response = $this->h->post($this->h->token(['different']))->assertStatus(403);
    expect($response->json())->toBe(['request_id' => $response->json('request_id'), 'error' => 'forbidden']);
    $token = $this->h->token();
    $id = explode('|', $token)[0];
    // Direct calls preserve the limiter, unlike independent harness requests.
    $headers = ['Authorization' => 'Bearer '.$token];
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/v1/extractions', ['document_text' => 'synthetic'], $headers)->assertStatus(201);
    }
    $response = $this->postJson('/api/v1/extractions', ['document_text' => 'synthetic'], $headers)->assertStatus(429)->assertHeader('Retry-After');
    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0);
    expect($response->json())->toBe(['request_id' => $response->json('request_id'), 'error' => 'too_many_requests']);
    $this->h->post($this->h->token())->assertStatus(201);
});

test('transport validation fields are fixed and request identifiers are safe', function () {
    $token = $this->h->token();
    foreach ([
        ['{}', ['Content-Type' => 'text/plain'], 415, 'unsupported_media_type', null],
        [str_repeat('x', 65537), [], 413, 'payload_too_large', null],
        ['{', [], 400, 'malformed_json', null],
        ['[]', [], 422, 'validation_failed', ['name' => '$', 'rule' => 'object']],
        ['{}', [], 422, 'validation_failed', ['name' => 'document_text', 'rule' => 'required']],
        ['{"document_text":""}', [], 422, 'validation_failed', ['name' => 'document_text', 'rule' => 'min']],
        [json_encode(['document_text' => str_repeat('x', 20001)]), [], 422, 'validation_failed', ['name' => 'document_text', 'rule' => 'max']],
        ['{"document_text":3}', [], 422, 'validation_failed', ['name' => 'document_text', 'rule' => 'string']],
        ['{"document_text":"synthetic","SENTINEL_KEY_7f3a":1}', [], 422, 'validation_failed', ['name' => '$', 'rule' => 'prohibited_key']],
    ] as [$raw, $headers, $status, $error, $field]) {
        $response = $this->h->request($raw, $token, $headers)->assertStatus($status)->assertHeader('X-Request-Id');
        expect($response->json())->toBe(['request_id' => $response->json('request_id'), 'error' => $error, ...($field === null ? [] : ['fields' => [$field]])]);
    }
    $uuid = (string) Str::uuid();
    $response = $this->h->post($token, headers: ['X-Request-Id' => $uuid])->assertStatus(201)->assertHeader('X-Request-Id', $uuid);
    expect($response->json('request_id'))->toBe($uuid);
    expect(DB::table('extraction_runs')->where('id', $response->json('run_id'))->value('request_id'))->toBe($uuid);
    $response = $this->h->post($token, headers: ['X-Request-Id' => 'invalid-synthetic'])->assertStatus(201);
    expect(Str::isUuid($response->json('request_id'), version: 4))->toBeTrue();
    expect($response->headers->get('X-Request-Id'))->toBe($response->json('request_id'));
});

test('Prism request includes configured schema timeout system prompt and provider options', function () {
    $this->h->prism();
    config(['intake.extraction.provider_options' => ['use_tool_calling' => false]]);
    $fake = Prism::fake([StructuredResponseFake::make()->withText(ExtractionCases::raw())->withStructured(json_decode(ExtractionCases::raw(), true))->withUsage(new Usage(123, 45))]);
    $response = $this->h->post($this->h->token())->assertStatus(201);
    $row = DB::table('extraction_runs')->where('id', $response->json('run_id'))->first();
    expect($row->input_tokens)->toBe(123);
    expect($row->output_tokens)->toBe(45);
    $fake->assertRequest(function (array $requests) {
        expect($requests)->toHaveCount(1);
        expect($requests[0]->schema()->name())->toBe('order_extraction_v1');
        expect($requests[0]->clientOptions())->toBe(['timeout' => 17]);
        expect($requests[0]->providerOptions())->toBe(['use_tool_calling' => false]);
        expect($requests[0]->systemPrompts()[0]->content)->toBe(file_get_contents(resource_path('prompts/extraction_p1.md')));
    });
});
