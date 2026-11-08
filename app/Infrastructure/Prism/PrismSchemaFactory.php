<?php

declare(strict_types=1);

namespace App\Infrastructure\Prism;

use App\Domain\Extraction\OrderExtractionContract;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\RawSchema;

final class PrismSchemaFactory
{
    public function make(): ObjectSchema
    {
        $properties = [];
        foreach (OrderExtractionContract::properties() as $name => $schema) {
            $properties[] = new RawSchema($name, $schema);
        }

        return new ObjectSchema(OrderExtractionContract::VERSION, 'Synthetic order extraction', $properties, OrderExtractionContract::KEYS, false);
    }
}
