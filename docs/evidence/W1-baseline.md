# W1 baseline evidence

Use synthetic fixtures only. Do not include tokens, authorization values, document text, model output, exception messages, or real data. Show any authorization header as `Bearer ***`. Fill this template from one run of [the owner run kit](../RUNBOOK-W1.md).

## Environment

- HEAD SHA:
- `php -v` observed result:
- `php -v` exit code:
- `composer -V` observed result:
- `composer -V` exit code:
- Gateway check line (must be exactly `INTAKE_GATEWAY=fake`):
- Gateway check exit code:

## Runbook steps

For each step, record the command as written in the runbook (for step 1, identify the GitHub Codespaces action and both commands). Redact any authorization value as `Bearer ***`.

### Step 1 — Codespace on main

- Command/action:
- Observed result:
- Exit code:
- HTTP status (if relevant): N/A
- Status (PASS / FAIL / BLOCKED / NOT_RUN / UNVERIFIED):

### Step 2 — Runtime versions

- Command:
- Observed result:
- Exit code:
- HTTP status (if relevant): N/A
- Status (PASS / FAIL / BLOCKED / NOT_RUN):

### Step 3 — Setup

- Command:
- Observed result:
- Exit code:
- HTTP status (if relevant): N/A
- Status (PASS / FAIL / BLOCKED / NOT_RUN):

### Step 4 — Fake gateway check

- Command:
- Observed result:
- Exit code:
- HTTP status (if relevant): N/A
- Status (PASS / FAIL / BLOCKED / NOT_RUN):

### Step 5 — Test suite

- Command:
- Observed result (include test and assertion counts):
- Exit code:
- HTTP status (if relevant): N/A
- Status (PASS / FAIL / BLOCKED / NOT_RUN):

### Step 6 — Server in second terminal

- Command:
- Observed result:
- Exit code:
- HTTP status (if relevant): N/A
- Status (PASS / FAIL / BLOCKED / NOT_RUN / UNVERIFIED):

### Step 7 — Token helper

- Command:
- Observed result (`token-set` or the exact `token-FAILED` line only):
- Exit code:
- HTTP status (if relevant): N/A
- Status (PASS / FAIL / BLOCKED / NOT_RUN):

### Step 8 — Request bodies

- Commands:
- Observed result:
- Exit code(s):
- HTTP status (if relevant): N/A
- Status (PASS / FAIL / BLOCKED / NOT_RUN):

### Step 9 — Fixture requests

Record each request separately. Use only `Bearer ***` in the command evidence.

| Fixture | Command | Observed result | Process exit (expected / observed) | HTTP status (expected / observed) | Status |
| --- | --- | --- | --- | --- | --- |
| valid order | | | 0 / | 201 / | |
| non-order | | | 0 / | 201 / | |
| unknown SKU defect | | | 0 / | 201 / | |

- Valid status output (expected `accepted_for_review` / observed):
- Non-order status output (expected `rejected_by_model` / observed):
- Defect status output (expected `rejected_by_validation` / observed):
- Defect error check (expected `sku_unknown found` / observed):

### Step 10 — Missing authorization

- Command:
- Observed result:
- Process exit (expected / observed): 0 /
- HTTP status (expected / observed): 401 /
- Status (PASS / FAIL / BLOCKED / NOT_RUN):

### Step 11 — Revocation and old-token retry

- Revocation command:
- Revocation observed result:
- Revocation exit code:
- Retry command (show authorization as `Bearer ***`):
- Retry observed result:
- Retry process exit (expected / observed): 0 /
- Retry HTTP status (expected / observed): 401 /
- Status (PASS / FAIL / BLOCKED / NOT_RUN):

### Step 12 — Latest stored run

- Command:
- Observed result (synthetic record only):
- Exit code:
- HTTP status (if relevant): N/A
- Status (PASS / FAIL / BLOCKED / NOT_RUN):

### Step 13 — Stop server and codespace

- Command/action:
- Observed result:
- Exit code:
- HTTP status (if relevant): N/A
- Status (PASS / FAIL / BLOCKED / NOT_RUN / UNVERIFIED):

## Reproduced failure

In the Owner's own copy only, change one rule (for example, the quantity upper bound) so an existing test fails. Do not commit the temporary change. Record the changed rule, failing test name, and failure result; restore the rule and record the green rerun.

- Rule changed:
- Failing test name:
- Failure observed (no exception text or sensitive data):
- Failure run exit code:
- Rule restored:
- Green rerun observed result:
- Green rerun exit code:

## Checks not run

- Check(s):
- Reason:

## Owner explanation of the request flow

Write three to five sentences in the Owner's own words, describing how a synthetic document enters the request, how the fake gateway and validation determine its outcome, and how the result is stored. Do not include document text, tokens, or real data.

> 
