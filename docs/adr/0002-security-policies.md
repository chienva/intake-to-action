# ADR 0002: W1 security boundaries

Accepted: 2026-11-08.

Gitleaks 8.24.2 is pinned. Download its Linux x64 archive and the same release's checksums file over verified HTTPS; select exactly the archive's entry and verify SHA-256 before extraction. The release LICENSE first line is `MIT License`; print it and `gitleaks version`. Every scan uses `--redact=100 --exit-code=42 --report-format=json --report-path=...`. Full history must exit 0; canary must exit 42 with a `github-pat` finding; clean control must exit 0 with an empty JSON list. All other outcomes fail, including scanner errors. Canary data is generated at runtime outside the checkout and never printed. No scan ignores or custom rules are introduced.

Composer audit uses `--abandoned=fail`; no audit waivers in W1: an advisory stops work for an Owner decision. composer.json contains neither config.audit nor config.policy.

The standalone lab preflight reads only the process environment and .env text, refuses unsafe values, URLs, interpolation, cached configuration and symlinked .env files, and runs before framework scripts. Last .env assignment wins; quoting is resolved before validation. Its three Composer hooks protect install/update/dump-autoload. The first registered boot provider enforces local/testing + SQLite + canonical database/database.sqlite for artisan and HTTP; :memory: is testing-only. Guard failures expose only fixed reason codes; HTTP returns 503 with the fixed refusal and does not log the exception. Setup never uses migrate:fresh. LabSeeder is empty and idempotent.

covered are Composer install/update/dump-autoload with scripts enabled, codex-setup.sh, codex-check.sh, CI, every artisan command and every HTTP request in any APP_ENV; not covered are Composer with --no-scripts, running arbitrary PHP files directly with php, and edited vendor code — these are outside the trusted entrypoints and are never used by prompts. HTTP coverage is proven at entrypoint level (public/index.php executed as a process); W1 has no HTTP-server transport test.

W1 exposes no registration, login or token-issuing route. Later token issuance is restricted to tests or the exact documented shell function: use synthetic owner@example.test, one-day TTL, capture without echo, clear INTAKE_TOKEN on failure and report only token-set or a fixed failure with exit code. The AGENTS definition must match the snippet byte for byte. Revoke expired or unused tokens; no production or real-user token lifecycle is introduced here.
