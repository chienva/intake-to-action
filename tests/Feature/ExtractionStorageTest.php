<?php

use App\Domain\Extraction\ExtractionStatus;
use App\Domain\Extraction\OrderExtractionContract;
use App\Infrastructure\Catalogue\EloquentCatalogueRepository;
use App\Infrastructure\Prism\PrismSchemaFactory;
use App\Models\CatalogueItem;
use App\Models\ExtractionRun;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\LabSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('schema matches committed serialized snapshot', function () {
    $schema = (new PrismSchemaFactory)->make();
    expect($schema->name())->toBe(OrderExtractionContract::VERSION);
    expect(json_encode($schema->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL)
        ->toBe(file_get_contents(__DIR__.'/../Fixtures/schema/order_extraction_v1.json'));
    expect(array_keys($schema->toArray()['properties']))->toBe(OrderExtractionContract::KEYS);
});

test('SQLite migration lifecycle and repeated seed preserve catalogue checksum', function () {
    $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    $this->seed(LabSeeder::class);
    expect(CatalogueItem::count())->toBe(20);
    $hash = fn () => hash('sha256', CatalogueItem::orderBy('sku')->get()->toJson());
    $initial = $hash();
    $this->artisan('migrate:rollback', ['--force' => true])->assertExitCode(0);
    expect(Schema::hasTable('catalogue_items'))->toBeFalse();
    expect(Schema::hasTable('extraction_runs'))->toBeFalse();
    $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    $this->seed(LabSeeder::class);
    expect($hash())->toBe($initial);
    for ($i = 0; $i < 2; $i++) {
        $this->seed(CatalogueSeeder::class);
        expect($hash())->toBe($initial);
    }
    $catalogue = new EloquentCatalogueRepository;
    expect($catalogue->contains('ABC-0001'))->toBeTrue();
    expect($catalogue->contains('ZZZ-9999'))->toBeFalse();
});

test('extraction run rejects updates and retains its original row', function () {
    $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    $run = ExtractionRun::create([
        'request_id' => (string) Str::uuid(), 'input_sha256' => hash('sha256', 'synthetic'), 'input_length' => 9,
        'status' => ExtractionStatus::AcceptedForReview, 'errors' => [], 'order' => null,
        'schema_version' => OrderExtractionContract::VERSION, 'prompt_version' => 'synthetic_v1', 'gateway' => 'synthetic',
    ]);
    $original = (array) DB::table('extraction_runs')->where('id', $run->id)->first();
    expect($run->id)->toHaveLength(26);
    expect($run->status_set)->toBe('v1');
    foreach (['status' => ExtractionStatus::RejectedByModel, 'status_set' => 'v2', 'errors' => [['code' => 'synthetic', 'path' => '$']], 'order' => ['synthetic' => true]] as $key => $value) {
        expect(fn () => $run->update([$key => $value]))->toThrow(LogicException::class, 'Extraction runs are immutable');
        $run->setAttribute($key, $value);
        expect(fn () => $run->save())->toThrow(LogicException::class, 'Extraction runs are immutable');
        expect((array) DB::table('extraction_runs')->where('id', $run->id)->first())->toBe($original);
        $run->refresh();
    }
    expect(fn () => $run->saveQuietly())->toThrow(LogicException::class, 'Extraction runs are immutable');
    expect(fn () => $run->increment('input_length'))->toThrow(LogicException::class, 'Extraction runs are immutable');
    expect(fn () => ExtractionRun::whereKey($run->id)->update(['status_set' => 'v2']))->toThrow(LogicException::class, 'Extraction runs are immutable');
    expect(fn () => ExtractionRun::whereKey($run->id)->increment('input_length'))->toThrow(LogicException::class, 'Extraction runs are immutable');
    expect((array) DB::table('extraction_runs')->where('id', $run->id)->first())->toBe($original);
    expect(Schema::getColumnListing('extraction_runs'))->toBe(['id', 'request_id', 'user_id', 'input_sha256', 'input_length', 'status', 'status_set', 'errors', 'order', 'schema_version', 'prompt_version', 'gateway', 'provider', 'model', 'duration_ms', 'input_tokens', 'output_tokens', 'created_at']);
});
