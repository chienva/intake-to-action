# W1 owner run kit

Use synthetic fixtures only. The gateway check below must confirm the fake gateway before sending any request. No LLM call, API key, or cost is involved.

Run steps 1–5 and 7–12 in the first terminal. Step 6 runs in a second terminal. Do not enable shell tracing (`set -x`), print `INTAKE_TOKEN`, or paste its value anywhere. In evidence, represent the authorization header as `Bearer ***`.

1. On GitHub, open `chienva/intake-to-action` → **Code** → **Codespaces**. Open the existing codespace on `main`, or create a codespace on `main`. Open a terminal and run:

   ```bash
   git pull origin main
   git rev-parse HEAD
   ```

   Expected: `git pull` exits 0; `git rev-parse HEAD` prints the pulled commit SHA. Record that SHA.

2. Check the PHP and Composer versions:

   ```bash
   php -v
   composer -V
   ```

   Expected: PHP is 8.3 or higher and Composer prints its version. Record both outputs.

3. Set up the project:

   ```bash
   bash scripts/codex-setup.sh
   ```

   Expected: exit 0. It is safe to re-run this setup command.

4. Confirm the fake gateway before any request:

   ```bash
   v=$(grep -E '^INTAKE_GATEWAY=' .env); if [ "$v" = "INTAKE_GATEWAY=fake" ]; then echo "$v"; else echo "FAIL: gateway check" >&2; false; fi
   ```

   Expected: prints `INTAKE_GATEWAY=fake`, exit 0. Otherwise stderr prints `FAIL: gateway check`, exit 1; stop and do not send any request. The check leaves the terminal open.

5. Run the suite:

   ```bash
   php artisan test
   ```

   Expected: exit 0. Record the test and assertion counts.

6. In a second terminal, start the server and leave it running:

   ```bash
   INTAKE_GATEWAY=fake php artisan serve --port=8000
   ```

   Expected: the terminal shows a line containing `INFO Server running on [http://127.0.0.1:8000].` If that line does not appear, if any other port appears (including 8001), or if an error such as address already in use appears, stop the server with Ctrl+C, mark Step 6 BLOCKED, and do not continue to Step 7 or send any request until port 8000 is free.

7. Back in the first terminal, obtain a short-lived owner token using the supplied helper:

   ```bash
   source docs/snippets/intake_token.sh && intake_token
   ```

   Expected: `token-set`. If it prints `token-FAILED`, stop and record that line. Never echo or paste the token.

8. Build three JSON request bodies from the synthetic fixtures, without `jq`:

   ```bash
   php -r 'echo json_encode(["document_text" => file_get_contents($argv[1])]);' resources/fixtures/orders/valid_01.txt > /tmp/req_valid.json
   php -r 'echo json_encode(["document_text" => file_get_contents($argv[1])]);' resources/fixtures/orders/not_an_order_01.txt > /tmp/req_reject.json
   php -r 'echo json_encode(["document_text" => file_get_contents($argv[1])]);' resources/fixtures/orders/defect_unknown_sku_01.txt > /tmp/req_defect.json
   ```

   Expected: all three commands exit 0 and create the request files under `/tmp`.

9. Send each fixture with the fake gateway. Each `curl` command must start with `--disable`; do not add verbose or trace options. Run each command separately and record its exit code and HTTP status. In written evidence, redact the authorization value as `Bearer ***`.

   Valid order:

   ```bash
   curl --disable -sS -o /tmp/out.json -w '%{http_code}\n' -H "Authorization: Bearer $INTAKE_TOKEN" -H 'Content-Type: application/json' -H 'Accept: application/json' --data-binary @/tmp/req_valid.json http://127.0.0.1:8000/api/v1/extractions
   php -r '$d = json_decode(file_get_contents("/tmp/out.json"), true); $s = is_array($d) ? ($d["status"] ?? null) : null; if ($s !== "accepted_for_review") { fwrite(STDERR, "FAIL: status missing or unexpected\n"); exit(1); } echo $s, PHP_EOL;'
   ```

   Expected: `curl` exits 0 and prints `201`; the status command prints `accepted_for_review` and exits 0. Otherwise it prints `FAIL: status missing or unexpected` to stderr and exits 1; stop.

   Non-order:

   ```bash
   curl --disable -sS -o /tmp/out.json -w '%{http_code}\n' -H "Authorization: Bearer $INTAKE_TOKEN" -H 'Content-Type: application/json' -H 'Accept: application/json' --data-binary @/tmp/req_reject.json http://127.0.0.1:8000/api/v1/extractions
   php -r '$d = json_decode(file_get_contents("/tmp/out.json"), true); $s = is_array($d) ? ($d["status"] ?? null) : null; if ($s !== "rejected_by_model") { fwrite(STDERR, "FAIL: status missing or unexpected\n"); exit(1); } echo $s, PHP_EOL;'
   ```

   Expected: `curl` exits 0 and prints `201`; the status command prints `rejected_by_model` and exits 0. Otherwise it prints `FAIL: status missing or unexpected` to stderr and exits 1; stop.

   Unknown SKU defect:

   ```bash
   curl --disable -sS -o /tmp/out.json -w '%{http_code}\n' -H "Authorization: Bearer $INTAKE_TOKEN" -H 'Content-Type: application/json' -H 'Accept: application/json' --data-binary @/tmp/req_defect.json http://127.0.0.1:8000/api/v1/extractions
   php -r '$d = json_decode(file_get_contents("/tmp/out.json"), true); $s = is_array($d) ? ($d["status"] ?? null) : null; if ($s !== "rejected_by_validation") { fwrite(STDERR, "FAIL: status missing or unexpected\n"); exit(1); } echo $s, PHP_EOL;'
   php -r '$d = json_decode(file_get_contents("/tmp/out.json"), true); $e = is_array($d) ? ($d["errors"] ?? null) : null; if (!is_array($e) || !in_array("sku_unknown", array_column($e, "code"), true)) { fwrite(STDERR, "FAIL: sku_unknown missing\n"); exit(1); } echo "sku_unknown found", PHP_EOL;'
   ```

   Expected: `curl` exits 0 and prints `201`; the status command prints `rejected_by_validation` and exits 0; the final command prints `sku_unknown found` and exits 0. If either check fails, it prints its fixed `FAIL:` message to stderr, exits 1, and you stop.

10. Repeat the valid request without an authorization header:

    ```bash
    curl --disable -sS -o /tmp/out.json -w '%{http_code}\n' -H 'Content-Type: application/json' -H 'Accept: application/json' --data-binary @/tmp/req_valid.json http://127.0.0.1:8000/api/v1/extractions
    ```

    Expected: `curl` exits 0 and prints `401`.

11. Revoke all tokens for the synthetic owner, then retry the valid request with the old token:

    ```bash
    php artisan intake:revoke-token --all-for=owner@example.test
    curl --disable -sS -o /tmp/out.json -w '%{http_code}\n' -H "Authorization: Bearer $INTAKE_TOKEN" -H 'Content-Type: application/json' -H 'Accept: application/json' --data-binary @/tmp/req_valid.json http://127.0.0.1:8000/api/v1/extractions
    ```

    Expected: revocation exits 0; the second command exits 0 and prints `401`.

12. Read the latest stored run without Tinker:

    ```bash
    php -r '$p = new PDO("sqlite:database/database.sqlite"); $r = $p->query("SELECT id, request_id, status, errors FROM extraction_runs ORDER BY created_at DESC, id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC); if (!$r || $r["status"] !== "rejected_by_validation") { fwrite(STDERR, "FAIL: latest run missing or unexpected\n"); exit(1); } echo json_encode($r), PHP_EOL;'
    ```

    Expected: exit 0 and one JSON record for the latest `rejected_by_validation` run. Otherwise stderr prints `FAIL: latest run missing or unexpected` and the command exits 1; stop. Record the status; do not include real data.

13. Stop the server with **Ctrl+C** in the second terminal. In GitHub Codespaces, open the Codespaces menu and choose **Stop Current Codespace**. Stop it; do not delete it.

    Expected: the server stops and the codespace shows as stopped.
