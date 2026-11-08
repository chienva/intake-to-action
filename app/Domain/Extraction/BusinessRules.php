<?php

declare(strict_types=1);

namespace App\Domain\Extraction;

final readonly class BusinessRules
{
    public function __construct(private Clock $clock, private CatalogueRepository $catalogue) {}

    public function validate(array $payload): array
    {
        $errors = [];
        if ($payload['customer_code'] !== null && ! preg_match('/'.OrderExtractionContract::CUSTOMER_PATTERN.'/', $payload['customer_code'])) {
            $errors[] = new ValidationError('customer_code_invalid', '$.customer_code');
        }
        if ($payload['requested_delivery_date'] !== null) {
            $date = $payload['requested_delivery_date'];
            if (! preg_match('/'.OrderExtractionContract::DATE_PATTERN.'/', $date) || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
                $errors[] = new ValidationError('date_invalid', '$.requested_delivery_date');
            } else {
                $today = $this->clock->now()->setTimezone(new \DateTimeZone('Asia/Ho_Chi_Minh'))->setTime(0, 0);
                if ($date < $today->format('Y-m-d')) {
                    $errors[] = new ValidationError('date_in_past', '$.requested_delivery_date');
                } elseif ($date > $today->modify('+365 days')->format('Y-m-d')) {
                    $errors[] = new ValidationError('date_too_far', '$.requested_delivery_date');
                }
            }
        }
        foreach ($payload['line_items'] as $i => $item) {
            $path = '$.line_items.'.$i;
            if (! preg_match('/'.OrderExtractionContract::SKU_PATTERN.'/', $item['sku']) || ! $this->catalogue->contains($item['sku'])) {
                $errors[] = new ValidationError('sku_unknown', $path.'.sku');
            }
            if ($item['quantity'] < 1 || $item['quantity'] > 100000) {
                $errors[] = new ValidationError('quantity_out_of_range', $path.'.quantity');
            }
            if (! in_array($item['unit'], ['pcs', 'box', 'kg'], true)) {
                $errors[] = new ValidationError('unit_invalid', $path.'.unit');
            }
        }

        return ValidationError::sorted($errors);
    }
}
