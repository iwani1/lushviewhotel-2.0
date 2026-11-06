#!/usr/bin/env bash
#
# Import the production database dump into the LOCAL qloapps_live database.
#
# Usage:
#   dev/scripts/import-live-dump.sh /path/to/live_db.sql
#
# Hard safety rules, deliberately not configurable:
#   * The target database is always qloapps_live. It cannot be overridden.
#   * The connection is always the local compose "db" service. There is no host
#     parameter, so this script cannot be pointed at the live GreenGeeks server.
#   * The dump is validated before a single row is written.
#   * qloapps_fresh is never touched, so your clean reference install survives.
#
# The dump is NOT in this repository and must never be committed. Root
# .gitignore ignores *.sql, so keeping it anywhere under the repo is safe, but
# outside the repo is better.
set -euo pipefail

cd "$(dirname "$0")/../.."

TARGET_DB="qloapps_live"          # fixed on purpose
ROOT_PW="qlo_root_dev_only"       # matches docker-compose.yml
COMPOSE="docker compose"

dump="${1:-}"
if [ -z "$dump" ]; then
    echo "usage: dev/scripts/import-live-dump.sh /path/to/live_db.sql" >&2
    exit 1
fi
if [ ! -f "$dump" ]; then
    echo "error: no such file: $dump" >&2
    exit 1
fi

command -v docker >/dev/null 2>&1 || COMPOSE="podman compose"

echo "=== 1. Validating the dump before importing anything ==="
head -c 4000 "$dump" | grep -q "phpMyAdmin SQL Dump" \
    || { echo "error: this does not look like a phpMyAdmin export." >&2; exit 1; }
tables=$(grep -c '^CREATE TABLE' "$dump" || true)
[ "$tables" -gt 0 ] \
    || { echo "error: no CREATE TABLE statements found - refusing to import." >&2; exit 1; }
grep -q '`qlo_' "$dump" \
    || { echo "error: dump does not use the qlo_ table prefix - refusing to import." >&2; exit 1; }
size=$(stat -c%s "$dump" 2>/dev/null || stat -f%z "$dump")
echo "    phpMyAdmin export : yes"
echo "    CREATE TABLE      : $tables"
echo "    table prefix      : qlo_"
echo "    size              : $size bytes"
echo "    sha256            : $(sha256sum "$dump" | cut -d' ' -f1)"
echo

echo "=== 2. Confirming the database server is running ==="
$COMPOSE ps --services --filter "status=running" 2>/dev/null | grep -qx db \
    || { echo "error: the db container is not running. Start it with: docker compose up -d" >&2; exit 1; }
echo "    db container is up."
echo

echo "=== 3. Creating $TARGET_DB (fresh, empty) ==="
# DROP + CREATE so re-running this script always gives a clean import rather
# than layering rows on top of a previous attempt. Scoped to TARGET_DB only.
$COMPOSE exec -T db mariadb -uroot -p"$ROOT_PW" <<SQL
DROP DATABASE IF EXISTS \`${TARGET_DB}\`;
CREATE DATABASE \`${TARGET_DB}\` CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;
SQL
echo "    created."
echo

echo "=== 4. Importing (this takes a minute for a 16 MB dump) ==="
$COMPOSE exec -T db mariadb -uroot -p"$ROOT_PW" "$TARGET_DB" < "$dump"
imported=$($COMPOSE exec -T db mariadb -uroot -p"$ROOT_PW" -N -B \
    -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='${TARGET_DB}';")
echo "    tables now in $TARGET_DB: $imported"
echo

echo "=== 5. Applying migration 001 (widen password columns) ==="
# Required: the live schema stores passwd as varchar(32), but 1.8.0 hashes with
# bcrypt, which is 60 characters. See dev/migrations/001_*.up.sql for the full
# reasoning. Without this, re-hashing on login silently fails (STRICT mode) or
# truncates and locks the account out (non-strict mode).
$COMPOSE exec -T db mariadb -uroot -p"$ROOT_PW" "$TARGET_DB" \
    < dev/migrations/001_widen_password_columns.up.sql
$COMPOSE exec -T db mariadb -uroot -p"$ROOT_PW" -N -B "$TARGET_DB" -e "
    SELECT CONCAT('    ', TABLE_NAME, '.passwd is now varchar(', CHARACTER_MAXIMUM_LENGTH, ')')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA='${TARGET_DB}'
      AND TABLE_NAME IN ('qlo_employee','qlo_customer')
      AND COLUMN_NAME='passwd';"
echo

echo "=== Done ==="
echo "  Imported into: $TARGET_DB"
echo "  Your clean install (qloapps_fresh) was not modified."
echo
echo "  Switch the app to this data with:   dev/scripts/use-db.sh live"
echo "  Then set a password you know with:  dev/scripts/set-admin-password.php <email> <new-password>"
echo
echo "  IMPORTANT: the imported staff passwords cannot be used to log in. They are"
echo "  MD5 digests salted with the LIVE _COOKIE_KEY_, which lives in the production"
echo "  config/settings.inc.php and was never exported. You must set a new one."
