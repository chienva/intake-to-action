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
   grep -E '^INTAKE_GATEWAY=' .env
   ```

   Expected: exactly `INTAKE_GATEWAY=fake`. If it prints anything else or nothing, stop and do not continue with any request.

5. Run the suite:

   ```bash
   php artisan test
   ```

   Expected: exit 0. Record the test and assertion counts.

6. In a second terminal, start the server and leave it running:

   ```bash
   INTAKE_GATEWAY=fake php artisan serve --port=8000
   ```

   Expected: the development server starts on port 8000 and remains running.

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
   php -r 'echo json_decode(file_get_contents("/tmp/out.json"), true)["status"], PHP_EOL;'
   ```

   Expected: `curl` exits 0 and prints `201`; the status command prints `accepted_for_review`.

   Non-order:

   ```bash
   curl --disable -sS -o /tmp/out.json -w '%{http_code}\n' -H "Authorization: Bearer $INTAKE_TOKEN" -H 'Content-Type: application/json' -H 'Accept: application/json' --data-binary @/tmp/req_reject.json http://127.0.0.1:8000/api/v1/extractions
   php -r 'echo json_decode(file_get_contents("/tmp/out.json"), true)["status"], PHP_EOL;'
   ```

   Expected: `curl` exits 0 and prints `201`; the status command prints `rejected_by_model`.

   Unknown SKU defect:

   ```bash
   curl --disable -sS -o /tmp/out.json -w '%{http_code}\n' -H "Authorization: Bearer $INTAKE_TOKEN" -H 'Content-Type: application/json' -H 'Accept: application/json' --data-binary @/tmp/req_defect.json http://127.0.0.1:8000/api/v1/extractions
   php -r 'echo json_decode(file_get_contents("/tmp/out.json"), true)["status"], PHP_EOL;'
   php -r 'echo in_array("sku_unknown", array_column(json_decode(file_get_contents("/tmp/out.json"), true)["errors"], "code"), true) ? "sku_unknown found" : "sku_unknown missing", PHP_EOL;'
   ```

   Expected: `curl` exits 0 and prints `201`; the status command prints `rejected_by_validation`; the final command prints `sku_unknown found`.

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
    php -r '$p = new PDO("sqlite:database/database.sqlite"); foreach ($p->query("SELECT id, request_id, status, errors FROM extraction_runs ORDER BY created_at DESC, id DESC LIMIT 1", PDO::FETCH_ASSOC) as $r) { echo json_encode($r), PHP_EOL; }'
    ```

    Expected: exit 0 and one JSON record for the most recent run. Record the status; do not include real data.

13. Stop the server with **Ctrl+C** in the second terminal. In GitHub Codespaces, open the Codespaces menu and choose **Stop Current Codespace**. Stop it; do not delete it.

    Expected: the server stops and the codespace shows as stopped.
