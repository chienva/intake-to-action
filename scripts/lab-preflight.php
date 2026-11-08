<?php

// Deliberately standalone: do not load Composer or boot Laravel here.
$root = dirname(__DIR__);
$refuse = static function (string $reason): never {
    fwrite(STDOUT, 'REFUSED: '.$reason.PHP_EOL);
    exit(1);
};
$validPath = static function (string $path, ?string $environment) use ($root): bool {
    if ($path === ':memory:') {
        return $environment === 'testing';
    }
    $directory = realpath($root.'/database');

    return $directory !== false && $path !== ''
        && basename($path) === 'database.sqlite'
        && realpath(dirname($path)) === $directory
        && ! is_link($path);
};
$check = static function (array $values, ?string $environment) use ($refuse, $validPath): void {
    if (isset($values['APP_ENV']) && ! in_array($values['APP_ENV'], ['local', 'testing'], true)) {
        $refuse('environment');
    }
    if (isset($values['DB_CONNECTION']) && $values['DB_CONNECTION'] !== 'sqlite') {
        $refuse('driver');
    }
    foreach (['DB_URL', 'DATABASE_URL'] as $key) {
        if (isset($values[$key]) && $values[$key] !== '') {
            $refuse('database-url');
        }
    }
    if (isset($values['DB_DATABASE']) && ! $validPath($values['DB_DATABASE'], $environment)) {
        $refuse('database-path');
    }
};
$keys = ['APP_ENV', 'DB_CONNECTION', 'DB_URL', 'DATABASE_URL', 'DB_DATABASE'];
$process = [];
foreach ($keys as $key) {
    if (($value = getenv($key)) !== false) {
        $process[$key] = $value;
    }
}
$check($process, $process['APP_ENV'] ?? null);
foreach (array_merge([$root.'/bootstrap/cache/config.php', $root.'/bootstrap/cache/events.php'], glob($root.'/bootstrap/cache/routes-*.php')) as $cache) {
    if (file_exists($cache) || is_link($cache)) {
        $refuse('cached-config');
    }
}
$env = $root.'/.env';
if (is_link($env) || (file_exists($env) && (! is_file($env) || ! is_readable($env)))) {
    $refuse('env-file');
}
$values = [];
if (file_exists($env)) {
    $text = file_get_contents($env);
    if ($text === false) {
        $refuse('env-file');
    }
    // Parse assignments, preserving quoted whitespace and rejecting ambiguous syntax.
    $lines = preg_split('/\r\n|\n|\r/', $text);
    foreach ($lines as $line) {
        if (! preg_match('/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=(.*)$/', $line, $match)) {
            if (preg_match('/^\s*(?:export\s+)?(?:APP_ENV|DB_CONNECTION|DB_URL|DATABASE_URL|DB_DATABASE)\b/', $line)) {
                $refuse('env-file');
            }

            continue;
        }
        $key = $match[1];
        if (! in_array($key, $keys, true)) {
            continue;
        }
        $value = trim($match[2]);
        if (str_contains($value, '${')) {
            $refuse('env-file');
        }
        if (str_starts_with($value, '"') || str_starts_with($value, "'")) {
            $quote = $value[0];
            $pattern = $quote === '"' ? '/^"((?:[^"\\\\]|\\\\.)*)"\s*(?:#.*)?$/' : "/^'([^']*)'\\s*(?:#.*)?$/";
            if (! preg_match($pattern, $value, $quoted)) {
                $refuse('env-file');
            }
            $value = $quote === '"' ? strtr($quoted[1], ['\\"' => '"', '\\\\' => '\\', '\\n' => "\n", '\\r' => "\r"]) : $quoted[1];
        } else {
            $value = rtrim(preg_replace('/\s+#.*$/', '', $value));
            if (preg_match('/\s/', $value)) {
                $refuse('env-file');
            }
        }
        $values[$key] = $value;
    }
}
$check($values, $process['APP_ENV'] ?? $values['APP_ENV'] ?? null);
exit(0);
