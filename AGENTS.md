# Synthetic intake lab

Owner decides and merges. Claude designs. Codex implements and tests. ChatGPT reviews read-only in a separate session.

The first prompt line must be `Tài khoản: A` or `Tài khoản: B`; ask the Owner if missing.
No agent merges, deploys or changes repository settings.

This public repository and every runtime of it use synthetic data only. Real inputs and real personal data are forbidden. Nothing from the Owner's other repositories, including plans, budgets, personas, hypotheses, prospect data and reviews, may be copied here. Never commit secrets, .env, databases or tokens. Never log or print document_text, prompts, model output, exception messages, tokens or keys. The contract's fixed REFUSED/BLOCKED diagnostics are the only refusal messages permitted.

Use only the published environment's php and composer; never use a substitute runtime.
Never run intake:issue-token except inside tests or through this exact function; never echo INTAKE_TOKEN:

```bash
intake_token() { local t rc; t="$(php artisan intake:issue-token owner@example.test --ttl-days=1)"; rc=$?; if [ "$rc" -ne 0 ] || [ -z "$t" ]; then unset INTAKE_TOKEN; echo "token-FAILED rc=$rc" >&2; return 1; fi; export INTAKE_TOKEN="$t"; echo token-set; }
```

## PR contract

Branch: `tonytomalex/t2-bootstrap`
Title: `[ChatGPT-A] T2: Laravel 13 bootstrap, toolchain, CI`
The PR body starts with these four lines:

Executor: Codex A
Contract: W1 Execution Design rev 4.4, C-W1 v4.1
Base: 27bc26b747c0d3d88ab5166db0e04a44cecd9215
Agent: chatgpt-a

Run the start gate first and run `EXPECTED_BASE=27bc26b747c0d3d88ab5166db0e04a44cecd9215 bash scripts/codex-check.sh` before opening a PR.

START GATE (task checkout /workspace/intake-to-action only): G0 command -v php composer must print /home/agent/.local/bin/php and /home/agent/.local/bin/composer, else "BLOCKED: runtime". G1 git rev-parse HEAD equals BASE_SHA, else "BLOCKED: base-mismatch". G2 git ls-remote origin refs/heads/main must print a SHA equal to BASE_SHA; command failure or empty output "BLOCKED: base-unverified" (print the error line, never credentials); different SHA "BLOCKED: base-mismatch". G4 git status --porcelain prints nothing. Temporary clones created for fresh-clone tests are checked only by git -C <clone> rev-parse HEAD equal to this branch's head.

## Common rules (verbatim)

R1 Public repository, synthetic data only. Never commit or print secrets, tokens, .env, databases or files under $HOME/.config. Never copy content from any other repository of the Owner.
R2 Do not merge, deploy, change repository settings or write to GitHub except pushing branch tonytomalex/t2-bootstrap and opening this PR.
R3 Run every command so its exit code is visible and record it. Never hide a failure: no "|| true", "set +e", ignored exit codes, skipped, deleted or weakened tests or checks.
R3a An expected-failure check is only one this prompt names with its expected non-zero exit code and expected message. Run it as a single command, read $? in the very next command, record "expected=<...>, observed=<...>, PASS|FAIL". PASS only on an exact match; anything else, including exit 0, is FAIL.
R4 Stop and report with evidence if php or composer is not the published environment's (/home/agent/.local/bin/php and /home/agent/.local/bin/composer; otherwise print "BLOCKED: runtime" and never substitute Docker images, downloaded binaries or any other runtime), if a dependency needs PHP > 8.3, an installed API differs from this contract, a check can pass only by weakening it, a contract rule seems contradictory, or any network operation fails. No workarounds: no proxies, mirrors, TLS changes, vendor patches.
R5 Never print environment variables, tokens or secrets.
R6 Run the start gate first; any failure means stop immediately and report the exact BLOCKED message.
Tools may write caches under /tmp (e.g. Pint, Codex sandbox); allowed, but list them in the report.
