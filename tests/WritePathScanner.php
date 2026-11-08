<?php

namespace Tests;

final class WritePathScanner
{
    public function violations(string $root): array
    {
        $violations = [];
        foreach (['app', 'bootstrap', 'config', 'routes', 'database'] as $directory) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$directory, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), '/database/migrations/')) {
                    continue;
                }
                $relative = substr($file->getPathname(), strlen($root) + 1);
                $source = '';
                foreach (token_get_all(file_get_contents($file->getPathname())) as $token) {
                    if (is_array($token)) {
                        if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                            continue;
                        }
                        $source .= $token[1];
                    } else {
                        $source .= $token;
                    }
                }
                if ($this->unsafe($source, $relative === 'app/Infrastructure/Persistence/RunRepository.php')) {
                    $violations[] = $relative;
                }
            }
        }
        sort($violations);

        return $violations;
    }

    public function unsafe(string $source, bool $owner = false): bool
    {
        $writes = '(?:insert(?:GetId|OrIgnore|Using)?|create|save(?:Quietly)?|update(?:OrCreate)?|delete|destroy|upsert|truncate|increment|decrement|forceDelete|firstOrCreate)';
        $table = preg_match('/[\x27"]extraction_runs[\x27"]/', $source) === 1;
        if ($table && preg_match('/(?:->|::)\s*(?:update\w*|delete\w*|destroy|truncate|upsert|increment|decrement)\s*\(/i', $source)) {
            return true;
        }
        // Also cover raw SQL aimed at this append-only table.
        if (preg_match('/\b(?:UPDATE\s+[\x22`]?extraction_runs|DELETE\s+FROM\s+[\x22`]?extraction_runs|TRUNCATE\s+(?:TABLE\s+)?[\x22`]?extraction_runs)\b/i', $source)) {
            return true;
        }
        if ($owner) {
            return false;
        }
        if (preg_match('/\bINSERT\s+(?:OR\s+\w+\s+)?INTO\s+[\x22`]?extraction_runs\b/i', $source)) {
            return true;
        }
        $references = preg_replace('/\bclass\s+ExtractionRun\b/', 'class ModelDeclaration', $source);
        if (preg_match('/\bExtractionRun\b/', $references) && preg_match('/(?:->|::)\s*'.$writes.'\s*\(/i', $source)) {
            return true;
        }
        if ($table && preg_match('/(?:->|::)\s*'.$writes.'\s*\(/i', $source)) {
            return true;
        }
        $dbAliases = ['DB'];
        if (preg_match_all('/use\s+Illuminate\\\\Support\\\\Facades\\\\DB\s+as\s+(\w+)/', $source, $aliases)) {
            $dbAliases = [...$dbAliases, ...$aliases[1]];
        }
        foreach ($dbAliases as $alias) {
            if (preg_match('/\b'.preg_quote($alias, '/').'::table\s*\(\s*[\x27"]extraction_runs[\x27"]/', $source)) {
                return true;
            }
        }
        $modelAliases = ['ExtractionRun'];
        if (preg_match_all('/use\s+App\\\\Models\\\\ExtractionRun\s+as\s+(\w+)/', $source, $aliases)) {
            $modelAliases = [...$modelAliases, ...$aliases[1]];
        }
        foreach ($modelAliases as $alias) {
            if (preg_match('/\b'.preg_quote($alias, '/').'::[^;]*\b'.$writes.'\s*\(/is', $source)) {
                return true;
            }
            if (preg_match_all('/(\$\w+)\s*=\s*(?:new\s+|(?:\\\\?App\\\\Models\\\\)?)'.preg_quote($alias, '/').'\b/', $source, $variables)) {
                foreach ($variables[1] as $variable) {
                    if (preg_match('/'.preg_quote($variable, '/').'\s*->\s*'.$writes.'\s*\(/i', $source)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
