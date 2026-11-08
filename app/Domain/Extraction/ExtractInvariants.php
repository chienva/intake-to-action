<?php

declare(strict_types=1);

namespace App\Domain\Extraction;

final class ExtractInvariants
{
    public function validate(array $payload): array
    {
        $errors = $payload['reject_reason'] !== null ? [new ValidationError('reject_reason_unexpected', '$.reject_reason')] : [];
        foreach (['customer_code', 'requested_delivery_date', 'currency'] as $key) {
            if ($payload[$key] === null) {
                $errors[] = new ValidationError('field_required', '$.'.$key);
            }
        }
        if ($payload['line_items'] === []) {
            $errors[] = new ValidationError('line_items_empty', '$.line_items');
        }

        return ValidationError::sorted($errors);
    }
}
