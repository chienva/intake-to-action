#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
blocked() { echo "BLOCKED: $1"; exit 69; }
php -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' || blocked php
if remote_result=$(timeout 30 git ls-remote "${CODEX_REMOTE:-origin}" refs/heads/main); then
    [[ -n "$remote_result" ]] || blocked base-unverified
else
    blocked base-unverified
fi
read -r remote_sha remote_ref <<< "$remote_result"
git cat-file -e "$remote_sha^{commit}" 2>/dev/null || blocked base-behind
git merge-base --is-ancestor "$remote_sha" HEAD || blocked base-behind
[[ -n "${EXPECTED_BASE:-}" ]] || blocked base-unspecified
[[ "$EXPECTED_BASE" == "$remote_sha" ]] || blocked base-mismatch
step() {
    local name="$1"
    shift
    if "$@"; then
        :
    else
        echo "FAIL: $name"
        exit 1
    fi
}
step preflight php scripts/lab-preflight.php
step composer-validate composer validate --strict
step platform composer check-platform-reqs
step pint ./vendor/bin/pint --test
step tests php artisan test
echo READY
