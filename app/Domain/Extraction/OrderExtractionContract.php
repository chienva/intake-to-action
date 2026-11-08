<?php

declare(strict_types=1);

namespace App\Domain\Extraction;

final class OrderExtractionContract
{
    public const VERSION = 'order_extraction_v1';

    public const KEYS = ['outcome', 'reject_reason', 'customer_code', 'requested_delivery_date', 'currency', 'line_items'];

    public const ITEM_KEYS = ['sku', 'quantity', 'unit'];

    public const REASONS = ['not_an_order', 'missing_required_fields', 'ambiguous', 'other'];

    public const CURRENCIES = ['JPY', 'VND', 'USD'];

    public const LIMITS = ['customer_code' => 16, 'requested_delivery_date' => 10, 'sku' => 16, 'unit' => 8];

    public const MAX_ITEMS = 50;

    public const CUSTOMER_PATTERN = '\\AC-[0-9]{3}\\z';

    public const DATE_PATTERN = '\\A[0-9]{4}-[0-9]{2}-[0-9]{2}\\z';

    public const SKU_PATTERN = '\\A[A-Z]{3}-[0-9]{4}\\z';

    public static function properties(): array
    {
        return [
            'outcome' => ['type' => 'string', 'enum' => ['extracted', 'rejected']],
            'reject_reason' => ['type' => ['string', 'null'], 'enum' => [...self::REASONS, null]],
            'customer_code' => ['type' => ['string', 'null'], 'description' => 'At most 16 bytes', 'pattern' => self::CUSTOMER_PATTERN],
            'requested_delivery_date' => ['type' => ['string', 'null'], 'description' => 'At most 10 bytes', 'pattern' => self::DATE_PATTERN],
            'currency' => ['type' => ['string', 'null'], 'enum' => [...self::CURRENCIES, null]],
            'line_items' => ['type' => 'array', 'minItems' => 0, 'maxItems' => self::MAX_ITEMS,
                'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => self::ITEM_KEYS,
                    'properties' => [
                        'sku' => ['type' => 'string', 'description' => 'At most 16 bytes', 'pattern' => self::SKU_PATTERN],
                        'quantity' => ['type' => 'integer', 'minimum' => PHP_INT_MIN, 'maximum' => PHP_INT_MAX],
                        'unit' => ['type' => 'string', 'description' => 'At most 8 bytes'],
                    ]]],
        ];
    }
}
