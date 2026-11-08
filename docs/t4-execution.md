# T4 extraction endpoint

POST `/api/v1/extractions` accepts an application/json object containing only
`document_text` (1–20,000 characters, at most 65,536 raw body bytes). An expiring
Sanctum token needs `extractions:create`. Ten requests per token are permitted
per minute. Responses carry an echoed valid UUID request id or a generated UUIDv4.

The gateway runs before any transaction. The unchanged T3 StatusResolver validates
the raw model bytes. The controller generates a ULID, records pending_run_id,
constructs the complete row and pre-encodes the response before RunRepository's
single transactional INSERT. A successful response is returned only after commit.
The request middleware preserves an already prepared response header.

RunStateReader reconciles exceptions using a new connection. A committed row is
identified in a 500 response; an absent row after StorageFailure produces 503;
an unavailable reconciliation query produces 500 with run_state unknown. Operators
can use `php artisan intake:run-state <request_id>` for an independent read.

## Implementation choices

- ExtractionContext contains requestId and nullable userId. ExtractionGateway is
  an interface in App\Domain\Extraction. No T3 DTO constructors were changed.
- FakeExtractionGateway hashes fixture text to find the existing expected.json
  response and resolves relative dates with the injected Clock.
- FreshConnection creates and purges an intake_fresh connection from the active
  connection's configuration. RunStateReader contains read-only queries.
- StorageFailure retains only causeClass; neither the source message nor previous
  exception is attached. RunRepository owns the extraction_runs write path.
- IntakeConfigValidator and InvalidIntakeConfiguration live in App\Support and
  App\Domain\Extraction respectively. IntakeExceptionHandler renders only this
  configuration exception's key to console stderr. Normal exception reporting is
  replaced in bootstrap/app.php with class, request id, file and line.
- TokenRepository owns token issuance and fresh-connection cleanup. Artisan
  commands separate stdout from stderr and never write token files. User gains
  Sanctum's HasApiTokens; OperatorSeeder upserts the synthetic operator.
- Test helpers T4Harness, RedactionNets, SqliteColumnScanner and WritePathScanner
  live under tests. Framework/database tests remain under tests/Feature.
- Fault injection uses test-only gateway/repository doubles, SQLite triggers and
  a PDO subclass whose commit fails before or after durability. No production
  fault switches or provider requests are involved.
- The lexical write scanner covers production directories (excluding migrations),
  table/query writes, model static and instance writes, aliases and raw SQL. Its
  subprocess mutation probe creates a temporary forbidden writer, requires exit 1
  naming that file, removes it and requires a green rerun.
- Redaction channel inventory includes every configured channel and stack member.
  Each is replaced with its own capturing Monolog handler; resolved channels are
  cleared. MessageLogged is independently observed. Log files, native PHP
  error_log, command stdout/stderr, HTTP headers/bodies and every SQLite column
  are scanned. The native error_log target is reapplied at test entry because
  PHPUnit installs its own target after setUp. Exception context is normalized
  with class/message/file/line/trace/previous; unrelated nonserializable closures
  do not prevent observing records.

## Applied contract amendments

1. Raw `null` plus structured null is rejected_by_validation with exactly
   raw_structured_mismatch at `$`. Raw `[]` plus structured [] has integrity ok
   and is rejected_by_validation with exactly schema_unparseable at `$`.
2. The 18 malformed group (c) mutants below use structured null and exactly
   raw_structured_mismatch at `$`. Other required mutants retain the decoded
   matching structured array. Integrity precedes JSON decoding in S0.
3. A throwing ExtractionGateway binding is the unexpected 500 test. A Prism
   provider/fake exception is caught by PrismExtractionGateway and becomes 502.
4. Successful issue-token stdout is the sole permitted plaintext appearance,
   captured only in memory and asserted to contain one Sanctum-format line.
   The same stderr and all other command outputs, logs, HTTP responses and every
   database column are scanned for absence. The capture pattern emits fixed
   success/failure markers without plaintext.
5. extraction.provider_options is an array defaulting to [], never sourced from
   the environment, and is passed through withProviderOptions. Truthy tool calling
   is refused. Configuration unit tests assert exact exception keys and valid
   configurations. The bogus-gateway boot probe requires exit 1 and exact stderr
   `intake.extraction.gateway\n`, with empty stdout.
6. Production with fake is tested at validator unit level only. The existing
   LabBootGuard remains first and refuses production at boot.

Malformed mutant identifiers:

- valid_01.txt:c:customer_code-8
- valid_01.txt:c:sku-8
- valid_01.txt:c:requested_delivery_date-8
- valid_02.txt:c:customer_code-8
- valid_02.txt:c:sku-8
- valid_02.txt:c:requested_delivery_date-8
- valid_03.txt:c:customer_code-8
- valid_03.txt:c:sku-8
- valid_03.txt:c:requested_delivery_date-8
- valid_04.txt:c:customer_code-8
- valid_04.txt:c:sku-8
- valid_04.txt:c:requested_delivery_date-8
- valid_05.txt:c:customer_code-8
- valid_05.txt:c:sku-8
- valid_05.txt:c:requested_delivery_date-8
- valid_06.txt:c:customer_code-8
- valid_06.txt:c:sku-8
- valid_06.txt:c:requested_delivery_date-8

## Verification scope

The suite covers accepted/model-rejected/validation-rejected responses, both
gateways, integrity precedence and strict JSON comparisons, all required T3
boundary mutants, provider failure, authentication/ability/expiry/revocation,
rate limiting, transport validation and request ids.

Storage cases include gateway outside a transaction, failure before pending id,
processed/provider insert failures, commit failure before durability, exception
after commit, durable commit reporting failure, failed existence queries after
commit and after StorageFailure, and a non-storage failure with a pending id.
Every boundary case reconciles with the independent run-state command.

Redaction injects independently into document_text, a model value, a model key,
Authorization, provider exception message, unexpected exception messages before
and after commit, a request key, and a process-issued token's plaintext. Both 500
injections also run with APP_DEBUG=true. Sentinels fail every contract field
pattern and enum. Each case first asserts its intended status or command exit.

Positive controls require each configured channel and stack member in nets 1 and
2 separately, runtime Log::build in net 1 and its file, emergency fallback in net
1 and laravel.log, native error_log in its redirected file, separate command
stdout/stderr markers, and exactly one class-only MarkerException record with
the message marker absent. Scanner coverage equals the real database schema; a
three-table fixture covers TEXT, INTEGER, BLOB and JSON columns and a missing-table
mutant must trigger the coverage assertion.

Token faults cover insert failure, durable commit reporting failure, failed
cleanup delete and count, idempotent name revocation, invalid lifetimes, captured
process success/failure and use of an issued token. Listing selects metadata only.

Intermediate verification failures were retained and fixed: the cleanup-count
fault hook initially vanished on reconnection; the channel creator initially
captured Laravel's rebound `$this`; direct PHP serialization encountered closure
objects in exception traces; PHPUnit's error_log redirection superseded setup.
No existing checks were skipped, removed or weakened.

## Local verification results

- Original start gate: setup exit 0; codex-check exit 0, READY, 93 tests and
  25,132 assertions. Resume HEAD and remote-main checks matched the released base.
- Completed T4 suite: 146 tests, 28,670 assertions, exit 0. Pint and diff whitespace
  checks exit 0. codex-check exit 0 and READY.
- Configuration boot R3a: expected 1, observed 1, PASS. Stdout empty; exact stderr
  is the key intake.extraction.gateway followed by one newline.
- Forbidden-writer R3a: expected 1, observed 1 naming app/T4WriteProbe.php, PASS.
  The probe is removed in finally and the filtered suite then exits 0.
- Intermediate suite runs had exits 1 or 2 while the test-harness faults described
  above were corrected. The first READY-output summary parser also failed with
  exit 1 on concatenated JSON records; a corrected parser exited 0 and confirmed
  the original codex-check exit 0 and READY. These were not waived checks.
