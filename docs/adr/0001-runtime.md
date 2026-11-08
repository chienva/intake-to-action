# ADR 0001: W1 runtime

Accepted: 2026-11-08.

Laravel 13 runs on PHP ^8.3. Composer platform.php is 8.3.0 before dependency resolution: resolving with the published PHP 8.4.1 runtime first can select Symfony 8.1, which needs PHP >=8.4.1. CI validates PHP 8.3 and 8.4 with Composer 2.11. SQLite keeps this synthetic lab local. Pest 4, its Laravel plugin and architecture plugin provide tests and the debug-helper ban. Prism is pinned to 0.102.*; W1 makes no live LLM calls and uses the fake gateway by default.

The published executables are /home/agent/.local/bin/php and /home/agent/.local/bin/composer. Never replace them with downloaded binaries or containers. CI uses pinned setup-php to exercise the required matrix.
