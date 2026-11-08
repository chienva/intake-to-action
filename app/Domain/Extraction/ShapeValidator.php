<?php

declare(strict_types=1);

namespace App\Domain\Extraction;

class ShapeValidator
{
    public function validate(array $payload): array
    {
        $errors = $this->keys($payload, OrderExtractionContract::KEYS, '$');
        foreach ($payload as $key => $value) {
            if (! in_array($key, OrderExtractionContract::KEYS, true)) {
                continue;
            }
            $path = '$.'.$key;
            if ($key === 'line_items') {
                if (! is_array($value) || ! array_is_list($value)) {
                    $errors[] = new ValidationError('type_invalid', $path);

                    continue;
                }
                if (count($value) > OrderExtractionContract::MAX_ITEMS) {
                    $errors[] = new ValidationError('line_items_too_many', $path);
                }
                foreach ($value as $i => $item) {
                    $itemPath = $path.'.'.$i;
                    if (! is_array($item)) {
                        $errors[] = new ValidationError('type_invalid', $itemPath);

                        continue;
                    }
                    array_push($errors, ...$this->keys($item, OrderExtractionContract::ITEM_KEYS, $itemPath));
                    foreach (OrderExtractionContract::ITEM_KEYS as $itemKey) {
                        if (array_key_exists($itemKey, $item)) {
                            array_push($errors, ...$this->scalar($item[$itemKey], $itemKey, $itemPath.'.'.$itemKey));
                        }
                    }
                }
            } else {
                array_push($errors, ...$this->scalar($value, $key, $path));
            }
        }

        return ValidationError::sorted($errors);
    }

    private function keys(array $values, array $allowed, string $path): array
    {
        $errors = [];
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $values)) {
                $errors[] = new ValidationError('key_missing', $path.'.'.$key);
            }
        }
        if (array_diff(array_keys($values), $allowed) !== []) {
            $errors[] = new ValidationError('key_unexpected', $path);
        }

        return $errors;
    }

    private function scalar(mixed $value, string $key, string $path): array
    {
        if ($value === null && in_array($key, ['reject_reason', 'customer_code', 'requested_delivery_date', 'currency'], true)) {
            return [];
        }
        if ($key === 'quantity' ? ! is_int($value) : ! is_string($value)) {
            return [new ValidationError('type_invalid', $path)];
        }
        $errors = [];
        $enums = ['outcome' => ['extracted', 'rejected'], 'reject_reason' => OrderExtractionContract::REASONS, 'currency' => OrderExtractionContract::CURRENCIES];
        if (isset($enums[$key]) && ! in_array($value, $enums[$key], true)) {
            $errors[] = new ValidationError('enum_invalid', $path);
        }
        if (isset(OrderExtractionContract::LIMITS[$key]) && strlen($value) > OrderExtractionContract::LIMITS[$key]) {
            $errors[] = new ValidationError('string_too_long', $path);
        }

        return $errors;
    }
}
