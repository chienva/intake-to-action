<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class LabState extends Command
{
    protected $signature = 'intake:lab-state';

    protected $description = 'Hash the local lab environment, schema and rows without disclosing them';

    public function handle(): int
    {
        $hash = hash_init('sha256');
        $append = static function (string $bytes) use ($hash): void {
            hash_update($hash, strlen($bytes).':'.$bytes);
        };
        $append(is_file(base_path('.env')) ? file_get_contents(base_path('.env')) : '');
        $pdo = DB::connection('sqlite')->getPdo();
        $schema = $pdo->query('SELECT * FROM sqlite_master ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        $append(serialize($schema));
        foreach ($schema as $entry) {
            if ($entry['type'] === 'table') {
                $table = '"'.str_replace('"', '""', $entry['name']).'"';
                $append($entry['name']);
                $append(serialize($pdo->query('SELECT * FROM '.$table.' ORDER BY rowid')->fetchAll(PDO::FETCH_ASSOC)));
            }
        }
        $this->line(hash_final($hash));

        return self::SUCCESS;
    }
}
