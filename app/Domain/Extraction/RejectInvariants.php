<?php

declare(strict_types=1);

namespace App\Domain\Extraction;

final class RejectInvariants
{
    public function validate(array $payload): array
    {
        $errors = $payload['reject_reason'] === null ? [new ValidationError('reject_reason_missing', '$.reject_reason')] : [];
        foreach (['customer_code', 'requested_delivery_date', 'currency', 'line_items'] as $key) {
            if ($payload[$key] !== ($key === 'line_items' ? [] : null)) {
                $errors[] = new ValidationError('reject_payload_inconsistent', '$.'.$key);
            }
        }

        return ValidationError::sorted($errors);
    }
}
