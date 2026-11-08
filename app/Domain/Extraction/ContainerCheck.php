<?php

declare(strict_types=1);

namespace App\Domain\Extraction;

class ContainerCheck
{
    public function __construct(private readonly DuplicateKeyScanner $scanner = new DuplicateKeyScanner) {}

    public function validate(mixed $raw, GatewayIntegrity $integrity = GatewayIntegrity::Ok): ContainerResult
    {
        $code = match ($integrity) {
            GatewayIntegrity::TextMissing => 'raw_text_unavailable',
            GatewayIntegrity::Refusal => 'model_refusal',
            GatewayIntegrity::StructuredMismatch => 'raw_structured_mismatch',
            GatewayIntegrity::Ok => null,
        };
        if ($code !== null) {
            return $this->failure($code);
        }
        if (! is_string($raw)) {
            return $this->failure('schema_unparseable');
        }
        try {
            $object = json_decode($raw, false, 16, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException) {
            return $this->failure('schema_unparseable');
        }
        $duplicates = $this->scanner->scan($raw);
        if ($duplicates !== []) {
            return new ContainerResult(null, $duplicates);
        }
        if (! $object instanceof \stdClass) {
            return $this->failure('schema_unparseable');
        }
        if (property_exists($object, 'line_items')) {
            if (! is_array($object->line_items)) {
                return $this->failure('type_invalid', '$.line_items');
            }
            foreach ($object->line_items as $i => $item) {
                if (! $item instanceof \stdClass) {
                    return $this->failure('type_invalid', '$.line_items.'.$i);
                }
            }
        }

        return new ContainerResult(json_decode($raw, true, 16, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING), []);
    }

    private function failure(string $code, string $path = '$'): ContainerResult
    {
        return new ContainerResult(null, [new ValidationError($code, $path)]);
    }
}
