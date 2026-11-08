<?php

namespace App\Support;

final class LabPolicy
{
    public static function validDatabase(mixed $path, string $environment, string $root): bool
    {
        if (! is_string($path) || $path === '') {
            return false;
        }

        if ($path === ':memory:') {
            return $environment === 'testing';
        }

        $directory = realpath($root.'/database');

        return $directory !== false
            && basename($path) === 'database.sqlite'
            && realpath(dirname($path)) === $directory
            && ! is_link($path);
    }

    public static function refusal(): ?string
    {
        $environment = app()->environment();
        if (! in_array($environment, ['local', 'testing'], true)) {
            return 'environment';
        }

        if (config('database.default') !== 'sqlite') {
            return 'driver';
        }

        // Resolve URLs through Laravel before checking the effective connection.
        $connection = app('db')->connection('sqlite')->getConfig();
        if (($connection['driver'] ?? null) !== 'sqlite') {
            return 'driver';
        }

        if (! self::validDatabase($connection['database'] ?? null, $environment, base_path())) {
            return 'database-path';
        }

        return null;
    }
}
