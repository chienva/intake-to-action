#!/usr/bin/env bash
set -euo pipefail
version=8.24.2
work=$(mktemp -d /tmp/intake-gitleaks.XXXXXXXX)
trap 'rm -rf "$work"' EXIT
archive="gitleaks_${version}_linux_x64.tar.gz"
release="https://github.com/gitleaks/gitleaks/releases/download/v${version}"
curl --fail --location --silent --show-error "$release/$archive" --output "$work/$archive"
curl --fail --location --silent --show-error "$release/gitleaks_${version}_checksums.txt" --output "$work/checksums.txt"
python3 - "$work" "$archive" <<'PY'
import hashlib, pathlib, sys
root, name = pathlib.Path(sys.argv[1]), sys.argv[2]
entries = [line.split()[0] for line in (root / 'checksums.txt').read_text().splitlines() if line.split()[-1] == name]
if len(entries) != 1 or hashlib.sha256((root / name).read_bytes()).hexdigest() != entries[0]:
    raise SystemExit('FAIL: gitleaks-checksum')
print('Gitleaks checksum: PASS')
PY
tar -xzf "$work/$archive" -C "$work"
"$work/gitleaks" version
head -n 1 "$work/LICENSE"
unset GITLEAKS_CONFIG GITLEAKS_CONFIG_TOML
if "$work/gitleaks" git --redact=100 --exit-code=42 --report-format=json --report-path="$work/history.json" --log-opts='--all' .; then
    observed=$?
else
    observed=$?
fi
if [[ "$observed" -eq 0 ]]; then
    echo 'history expected=0, observed=0, PASS'
else
    echo "history expected=0, observed=$observed, FAIL"
    exit 1
fi
mkdir "$work/canary" "$work/clean"
python3 - "$work/canary/token.txt" <<'PY'
import pathlib, secrets, string, sys
pathlib.Path(sys.argv[1]).write_text('ghp_' + ''.join(secrets.choice(string.ascii_letters + string.digits) for _ in range(36)) + '\n')
PY
printf 'Harmless synthetic control text.\n' > "$work/clean/control.txt"
if "$work/gitleaks" dir --redact=100 --exit-code=42 --report-format=json --report-path="$work/canary.json" "$work/canary"; then
    observed=$?
else
    observed=$?
fi
if [[ "$observed" -eq 42 ]]; then
    echo 'canary expected=42, observed=42, PASS'
else
    echo "canary expected=42, observed=$observed, FAIL"
    exit 1
fi
python3 - "$work/canary.json" <<'PY'
import json, sys
report = json.load(open(sys.argv[1]))
if not isinstance(report, list) or not any(item.get('RuleID') == 'github-pat' for item in report):
    raise SystemExit('FAIL: canary-rule')
print('canary rule=github-pat, PASS')
PY
if "$work/gitleaks" dir --redact=100 --exit-code=42 --report-format=json --report-path="$work/clean.json" "$work/clean"; then
    observed=$?
else
    observed=$?
fi
if [[ "$observed" -eq 0 ]]; then
    echo 'clean expected=0, observed=0, PASS'
else
    echo "clean expected=0, observed=$observed, FAIL"
    exit 1
fi
python3 - "$work/clean.json" <<'PY'
import json, sys
if json.load(open(sys.argv[1])) != []:
    raise SystemExit('FAIL: clean-report')
print('clean report=[], PASS')
PY
