<?php

declare(strict_types=1);

namespace App\Domain\Extraction;

/** Single pass over already validated JSON; never retain or expose member names. */
final class DuplicateKeyScanner
{
    public function scan(string $raw): array
    {
        $offset = 0;
        $duplicate = null;
        $this->value($raw, $offset, '$', true, false, $duplicate);

        return $duplicate === null ? [] : [new ValidationError('key_duplicate', $duplicate)];
    }

    private function whitespace(string $raw, int &$offset): void
    {
        $offset += strspn($raw, " \t\r\n", $offset);
    }

    private function token(string $raw, int &$offset): string
    {
        $start = $offset++;
        while ($raw[$offset] !== '"') {
            if ($raw[$offset] === '\\') {
                $offset++;
            }
            $offset++;
        }
        $offset++;

        return substr($raw, $start, $offset - $start);
    }

    private function value(string $raw, int &$offset, string $path, bool $root, bool $lineItems, ?string &$duplicate): void
    {
        $this->whitespace($raw, $offset);
        $kind = $raw[$offset];
        if ($kind === '{') {
            $offset++;
            $seen = [];
            $this->whitespace($raw, $offset);
            if ($raw[$offset] === '}') {
                $offset++;

                return;
            }
            do {
                $this->whitespace($raw, $offset);
                $name = json_decode($this->token($raw, $offset), false, 16, JSON_THROW_ON_ERROR);
                // Prefix avoids PHP's numeric-string array key coercion.
                $key = ':'.$name;
                if (isset($seen[$key]) && $duplicate === null) {
                    $duplicate = $path;
                }
                $seen[$key] = true;
                $this->whitespace($raw, $offset);
                $offset++; // colon
                $this->value($raw, $offset, $path, false, $root && $name === 'line_items', $duplicate);
                $this->whitespace($raw, $offset);
                $separator = $raw[$offset++];
            } while ($separator === ',');

            return;
        }
        if ($kind === '[') {
            $offset++;
            $index = 0;
            $this->whitespace($raw, $offset);
            if ($raw[$offset] === ']') {
                $offset++;

                return;
            }
            do {
                $this->value($raw, $offset, $lineItems ? '$.line_items.'.$index : $path, false, false, $duplicate);
                $index++;
                $this->whitespace($raw, $offset);
                $separator = $raw[$offset++];
            } while ($separator === ',');

            return;
        }
        if ($kind === '"') {
            $this->token($raw, $offset);

            return;
        }
        $offset += strcspn($raw, ",]} \t\r\n", $offset);
    }
}
