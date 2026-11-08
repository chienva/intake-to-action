<?php

namespace Tests;

use PDO;
use PHPUnit\Framework\Assert;

class SqliteColumnScanner
{
    public function scan(PDO $pdo): array
    {
        $result = [];
        foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN) as $table) {
            foreach ($pdo->query('PRAGMA table_info('.$this->quote($table).')')->fetchAll(PDO::FETCH_ASSOC) as $column) {
                $name = $column['name'];
                $values = $pdo->query('SELECT '.$this->quote($name).' FROM '.$this->quote($table))->fetchAll(PDO::FETCH_COLUMN);
                $result[$table.'.'.$name] = $values;
            }
        }
        ksort($result);

        return $result;
    }

    private function quote(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }

    public function assertCoverage(PDO $pdo, array $scan): void
    {
        $expected = [];
        foreach ($pdo->query("SELECT m.name AS table_name, p.name AS column_name FROM sqlite_master m JOIN pragma_table_info(m.name) p WHERE m.type = 'table' ORDER BY m.name, p.cid")->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $expected[] = $column['table_name'].'.'.$column['column_name'];
        }
        sort($expected);
        $actual = array_keys($scan);
        sort($actual);
        Assert::assertSame($expected, $actual, 'SQLite column scanner coverage');
    }
}
