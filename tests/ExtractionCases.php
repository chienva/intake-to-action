<?php

namespace Tests;

use App\Domain\Extraction\BusinessRules;
use App\Domain\Extraction\FixtureDateResolver;
use App\Domain\Extraction\FrozenClock;
use App\Domain\Extraction\GatewayIntegrity;
use App\Domain\Extraction\GatewayResult;
use App\Domain\Extraction\InMemoryCatalogueRepository;
use App\Domain\Extraction\StatusResolver;
use App\Domain\Extraction\ValidationResult;

final class ExtractionCases
{
    public static function clock(string $date = '2026-11-08'): FrozenClock
    {
        return new FrozenClock(new \DateTimeImmutable($date, new \DateTimeZone('Asia/Ho_Chi_Minh')));
    }

    public static function resolver(?FrozenClock $clock = null): StatusResolver
    {
        return new StatusResolver(new BusinessRules($clock ?? self::clock(), new InMemoryCatalogueRepository(array_map(fn ($i) => sprintf('ABC-%04d', $i), range(1, 20)))));
    }

    public static function fixtures(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../resources/fixtures/orders/expected.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function raw(): string
    {
        return (new FixtureDateResolver(self::clock()))->resolve(self::fixtures()['valid_01.txt']['raw']);
    }

    public static function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }

    public static function result(string $raw, GatewayIntegrity $integrity = GatewayIntegrity::Ok): ValidationResult
    {
        return self::resolver()->resolve(new GatewayResult($raw, $integrity));
    }

    /** group, raw, positive-preservation, original status */
    public static function mutations(): array
    {
        $cases = [];
        $dates = new FixtureDateResolver(self::clock());
        foreach (self::fixtures() as $name => $fixture) {
            if ($fixture['status'] !== 'accepted_for_review') {
                continue;
            }
            $raw = $dates->resolve($fixture['raw']);
            $base = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
            $add = function (string $group, string $label, string $text, bool $preserve = false) use (&$cases, $name, $fixture) {
                $cases[$name.':'.$group.':'.$label] = [$group, $text, $preserve, $fixture['status']];
            };
            foreach (array_keys($base) as $key) {
                $p = $base;
                unset($p[$key]);
                $add('a', 'drop-'.$key, self::encode($p));
                foreach ([1, 1.5, true, 'x', [], new \stdClass, null] as $i => $value) {
                    $p = $base;
                    $p[$key] = $value;
                    $add('a', 'replace-'.$key.'-'.$i, self::encode($p), $value === $base[$key]);
                }
            }
            $p = $base;
            $p['extra'] = true;
            $add('a', 'extra', self::encode($p));
            $p = $base;
            $p['outcome'] = 'maybe';
            $add('a', 'enum', self::encode($p));
            foreach (['sku', 'quantity', 'unit'] as $key) {
                $p = $base;
                unset($p['line_items'][0][$key]);
                $add('a', 'item-drop-'.$key, self::encode($p));
                foreach ([1, 1.5, true, 'x', [], new \stdClass, null] as $i => $value) {
                    $p = $base;
                    $p['line_items'][0][$key] = $value;
                    $add('a', 'item-replace-'.$key.'-'.$i, self::encode($p), $key === 'quantity' && $value === 1);
                }
            }
            $p = $base;
            $p['line_items'][0]['extra'] = true;
            $add('a', 'item-extra', self::encode($p));
            foreach (['"5"', '5.0', '1e2', 'true', '0', '100001', '99999999999999999999'] as $i => $number) {
                $add('a', 'quantity-'.$i, preg_replace('/"quantity":[0-9]+/', '"quantity":'.$number, $raw));
            }
            $p = $base;
            $p['line_items'] = array_fill(0, 51, $base['line_items'][0]);
            $add('a', '51-items', self::encode($p));
            $add('b', 'root-array', '[]');
            $add('b', 'root-wrapped', '['.$raw.']');
            foreach ([new \stdClass, (object) ['0' => (object) $base['line_items'][0]]] as $i => $value) {
                $p = $base;
                $p['line_items'] = $value;
                $add('b', 'items-object-'.$i, self::encode($p));
            }
            foreach ([[], ['ABC-0001', 5, 'pcs']] as $i => $value) {
                $p = $base;
                $p['line_items'][0] = $value;
                $add('b', 'item-array-'.$i, self::encode($p));
            }
            foreach (['customer_code', 'sku', 'requested_delivery_date'] as $key) {
                $original = $key === 'sku' ? $base['line_items'][0][$key] : $base[$key];
                $encoded = self::encode($original);
                $body = substr($encoded, 1, -1);
                $variants = [$body.'\\n', $body.'\\r', $body.'\\u000a', $body.'\\u0000', '\\u0020'.$body,
                    preg_replace('/[0-9]/', '\\uFF11', $body, 1), ' '.$body, preg_replace('/[0-9]/', '１', $body, 1), $body."\n"];
                foreach ($variants as $i => $text) {
                    $add('c', $key.'-'.$i, str_replace('"'.$key.'":'.$encoded, '"'.$key.'":"'.$text.'"', $raw));
                }
            }
            $p = $base;
            $p['SENTINEL_KEY_7f3a'] = 1;
            $add('d', 'root-sentinel', self::encode($p));
            $p = $base;
            $p['line_items'][0]['SENTINEL_KEY_7f3a'] = 1;
            $add('d', 'item-sentinel', self::encode($p));
            foreach (['"outcome":"extracted","outcome":"extracted"', '"outcome":"extracted","outcome":"rejected"', '"outcome":"rejected","outcome":"extracted"', '"outcome":"extracted","outc\\u006fme":"extracted"'] as $i => $members) {
                $add('f', 'outcome-'.$i, str_replace('"outcome":"extracted"', $members, $raw));
            }
            $sku = self::encode($base['line_items'][0]['sku']);
            $add('f', 'sku', str_replace('"sku":'.$sku, '"sku":'.$sku.',"sku":'.$sku, $raw));
            $items = self::encode($base['line_items']);
            foreach ([$items.',"line_items":{}', '{},"line_items":'.$items] as $i => $members) {
                $add('f', 'items-'.$i, str_replace('"line_items":'.$items, '"line_items":'.$members, $raw));
            }
            foreach ([(object) ['1' => (object) $base['line_items'][0]], (object) ['0' => (object) $base['line_items'][0], '1' => (object) $base['line_items'][0]]] as $i => $value) {
                $p = $base;
                $p['line_items'] = $value;
                $add('g', 'numeric-items-'.$i, self::encode($p));
            }
            $p = $base;
            $p['line_items'][0] = (object) ['0' => 'ABC-0001', '1' => 5, '2' => 'pcs'];
            $add('g', 'numeric-item', self::encode($p));
            $add('g', 'numeric-root', self::encode((object) ['0' => (object) $base]));
        }
        foreach (self::fixtures() as $name => $fixture) {
            if ($fixture['status'] !== 'rejected_by_model') {
                continue;
            }
            $base = json_decode($fixture['raw'], true, flags: JSON_THROW_ON_ERROR);
            foreach (['customer_code' => 'C-001', 'requested_delivery_date' => '2026-12-01', 'currency' => 'JPY', 'line_items-one' => [['sku' => 'ABC-0001', 'quantity' => 1, 'unit' => 'pcs']], 'line_items-two' => array_fill(0, 2, ['sku' => 'ABC-0001', 'quantity' => 1, 'unit' => 'pcs'])] as $key => $value) {
                $p = $base;
                $p[str_starts_with($key, 'line_items') ? 'line_items' : $key] = $value;
                $cases[$name.':e:'.$key] = ['e', self::encode($p), false, $fixture['status']];
            }
        }

        return $cases;
    }
}
