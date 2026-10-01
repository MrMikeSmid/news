#!/usr/bin/env bash
# Draait alle tests: nepservers + app lokaal. Gebruik: bash tests/run.sh
set -u
cd "$(dirname "$0")/.."
TMP=$(mktemp -d)
openssl req -x509 -newkey rsa:2048 -nodes -keyout "$TMP/key.pem" -out "$TMP/cert.pem" -days 2 -subj "/CN=localhost" 2>/dev/null
python3 tests/fixtures/fake_servers.py "$TMP/cert.pem" "$TMP/key.pem" > "$TMP/fixtures.log" 2>&1 &
FIX=$!
export DAGSTART_DB="$TMP/http.sqlite" DAGSTART_KEY="$TMP/http.key" DAGSTART_ALLOW_PRIVATE_FETCH=1
php -S localhost:8890 -t . > "$TMP/php.log" 2>&1 &
APP=$!
trap 'kill $FIX $APP 2>/dev/null' EXIT
sleep 1.5
FAIL=0
(unset DAGSTART_DB DAGSTART_KEY; php tests/mail.php) || FAIL=1
(unset DAGSTART_DB DAGSTART_KEY; php tests/calendar.php) || FAIL=1
php tests/http.php || FAIL=1
grep -iE "fatal|warning|notice|deprecated" "$TMP/php.log" | head -20
exit $FAIL
