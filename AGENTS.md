# Synthetic intake lab

Owner decides and merges. Claude designs. Codex implements and tests. ChatGPT reviews read-only in a separate session.

The first prompt line must be `Tài khoản: A` or `Tài khoản: B`; ask the Owner if missing.
The branch, PR title, PR metadata and BASE_SHA always come from the released task prompt. Never reuse these values from earlier tasks.

## Common rules

R1 This is a public repository. Use synthetic data only in the repository and every runtime of it. Real inputs and real personal data are forbidden. Nothing from any other repository of the Owner may be copied here. Never commit or print secrets, tokens, .env, databases or files under $HOME/.config.

R2 GitHub writes are limited to pushing the branch named in the released prompt (fast-forward only, never force) and opening or updating its PR, subject to any narrower task restrictions. No agent merges, deploys or changes repository settings.

R3 Run every command so its exit code is visible and record it. Never hide a failure: no ignored exit codes, `|| true`, `set +e`, skipped, deleted or weakened tests or checks.

R3a An expected-failure check is only one the released prompt names with its expected non-zero exit code and expected message. Run it as a single command, read `$?` in the very next shell command, and record expected and observed exit codes and messages with PASS or FAIL. PASS only on an exact match; anything else, including exit 0, is FAIL.

R4 Use only the published environment's php and composer. `command -v php composer` must print `/home/agent/.local/bin/php` and `/home/agent/.local/bin/composer`; otherwise print `BLOCKED: runtime` and stop. Never substitute Docker images, downloaded binaries or any other runtime. Stop and report evidence if a change outside the task's allowed files is needed, a check can pass only by weakening it, an instruction seems contradictory, or any network operation fails. No workarounds.

R5 Never print environment variables, tokens or secrets. Never log or print document_text, prompts, model output, exception messages, tokens or keys. Use only fixed refusal diagnostics specified by the contract.

R6 Run the start gate first. Any failure means stop immediately and report the exact BLOCKED message.

## Start gate and PR verification

Apply G0–G4 in the order and with the checkout and expected values specified in the released task prompt:

- G0: verify the published runtime paths as required by R4.
- G4: `git status --porcelain` must print nothing, otherwise `BLOCKED: dirty-checkout`.
- G2: verify remote main against BASE_SHA and any task branch against its prompt-supplied expected head using `git ls-remote`. Command failure or empty output means `BLOCKED: base-unverified`; a different SHA means `BLOCKED: base-mismatch`.
- G1: fetch and select the prompt's branch exactly as instructed, verify HEAD against the prompt's expected SHA, and require a clean checkout. A fetch failure means `BLOCKED: base-unverified`; a checkout or HEAD mismatch means `BLOCKED: base-mismatch`.
- G3: apply any additional start-gate checks defined by the released prompt; never infer task-specific values from an earlier task.

Before opening or updating a PR, run `EXPECTED_BASE=<BASE_SHA from the prompt> bash scripts/codex-check.sh` and require exit 0 with READY. Record every required verification and its exit code. Fresh-clone checks must verify the clone's HEAD against the new local branch head and follow the released prompt's setup and test requirements.

## Token handling

Never run intake:issue-token except inside tests or through this exact function; never echo INTAKE_TOKEN:

```bash
intake_token() { local t rc; t="$(php artisan intake:issue-token owner@example.test --ttl-days=1)"; rc=$?; if [ "$rc" -ne 0 ] || [ -z "$t" ]; then unset INTAKE_TOKEN; echo "token-FAILED rc=$rc" >&2; return 1; fi; export INTAKE_TOKEN="$t"; echo token-set; }
```

Tools may write caches under /tmp; list paths written outside the repository in the report.
