#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
php scripts/lab-preflight.php || exit 1
if [[ ! -e .env ]]; then
    cp .env.example .env
fi
php scripts/lab-preflight.php || exit 1
if composer install --no-interaction --prefer-dist; then
    :
else
    echo 'BLOCKED: composer'
    exit 69
fi
if php -r '$s = file_get_contents(".env"); preg_match_all("/^APP_KEY=(.*)$/m", $s, $m); $v = trim(end($m[1]) ?: "", " \t\r\n\"\x27"); exit($v === "" ? 0 : 1);'; then
    php artisan key:generate
fi
if [[ ! -e database/database.sqlite ]]; then
    touch database/database.sqlite
fi
php artisan intake:lab-guard || exit 1
php artisan migrate --force
php artisan db:seed --class='Database\Seeders\LabSeeder' --force
exit 0
