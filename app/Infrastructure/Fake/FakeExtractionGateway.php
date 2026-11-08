<?php

declare(strict_types=1);

namespace App\Infrastructure\Fake;

use App\Domain\Extraction\ExtractionContext;
use App\Domain\Extraction\ExtractionGateway;
use App\Domain\Extraction\FixtureDateResolver;
use App\Domain\Extraction\GatewayFailure;
use App\Domain\Extraction\GatewayIntegrity;
use App\Domain\Extraction\GatewayResult;

final readonly class FakeExtractionGateway implements ExtractionGateway
{
    public function __construct(private FixtureDateResolver $dates) {}

    public function extract(string $text, ExtractionContext $context): GatewayResult|GatewayFailure
    {
        $root = resource_path('fixtures/orders');
        $fixtures = json_decode(file_get_contents($root.'/expected.json'), true, flags: JSON_THROW_ON_ERROR);
        $hash = hash('sha256', $text);
        foreach ($fixtures as $name => $fixture) {
            if (hash_equals(hash_file('sha256', $root.'/'.$name), $hash)) {
                return new GatewayResult($this->dates->resolve($fixture['raw']), GatewayIntegrity::Ok);
            }
        }

        return new GatewayResult('{"outcome":"rejected","reject_reason":"not_an_order","customer_code":null,"requested_delivery_date":null,"currency":null,"line_items":[]}', GatewayIntegrity::Ok);
    }
}
