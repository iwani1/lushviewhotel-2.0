#!/usr/bin/env bash
#
# Build a 1.8.0 database from the 1.7.0 production copy.
#
#   dev/scripts/upgrade-live-copy.sh
#
# QloApps ships upgrade SQL only up to 1.6.1.0 (install-dev/upgrade/sql/), so
# there is no official 1.7.0 -> 1.8.0 delta. This script derives one instead,
# using the two databases that already exist locally:
#
#   qloapps_live   the imported production data, still on the 1.7.0 schema.
#                  READ ONLY. This script never writes to it.
#   qloapps_fresh  a working 1.8.0 install. Used as the schema reference.
#   qloapps_18     the output: a COPY of qloapps_live, upgraded to the 1.8.0
#                  schema. This is what the application is pointed at.
#
# What it does, in order:
#   1. copies qloapps_live into qloapps_18 (drop and recreate, same charset)
#   2. finds every table that exists in 1.8.0 but not in the copy, and creates
#      it WITH the reference rows from qloapps_fresh - those are the tables
#      1.8.0 needs populated to boot (booking statuses, business sources, cron
#      tasks, and so on)
#   3. finds every column that exists in 1.8.0 but not in the copy, and adds it
#      with 1.8.0's exact definition
#   4. reports columns whose type differs between the two versions, but does
#      NOT change them - converting live money or text columns is a decision,
#      not a side effect
#   5. writes everything it ran to dev/migrations/generated/ so it can be
#      reviewed, and applies it
#
# That directory is deliberately gitignored: the output is derived from the
# production schema and does not belong in a public repository.
#
# What this does NOT do: convert data inside shared tables, backfill new tables
# that reference existing bookings, or touch the live server. See
# docs/project/08-upgrade-to-1.8.0.md.
set -euo pipefail

cd "$(dirname "$0")/../.."

SRC="${1:-qloapps_live}"
DST="${2:-qloapps_18}"
REF="${3:-qloapps_fresh}"

ROOT_USER="${QLO_ROOT_USER:-root}"
ROOT_PASS="${QLO_ROOT_PASSWORD:-qlo_root_dev_only}"
OUT_DIR="dev/migrations/generated"
OUT_FILE="${OUT_DIR}/002_${SRC}_to_1_8_0.sql"

if [ "$SRC" = "$DST" ] || [ "$REF" = "$DST" ] || [ "$SRC" = "$REF" ]; then
    echo "error: source, destination and reference must be three different databases." >&2
    echo "       got SRC=$SRC DST=$DST REF=$REF" >&2
    exit 1
fi

# --------------------------------------------------------------------------
# Reach the database container
# --------------------------------------------------------------------------
ENG=""
for cand in podman docker; do
    if command -v "$cand" >/dev/null 2>&1 \
       && "$cand" exec -i qlo-db true >/dev/null 2>&1; then
        ENG="$cand"
        break
    fi
done
if [ -z "$ENG" ]; then
    echo "error: cannot reach the qlo-db container." >&2
    echo "       Start it first:  podman-compose up -d" >&2
    exit 1
fi

# -N no column names, -B tab separated: predictable output for scripting.
sql() { "$ENG" exec -i qlo-db mariadb -u"$ROOT_USER" -p"$ROOT_PASS" -N -B "$@"; }

have_db() {
    [ -n "$(sql -e "SELECT SCHEMA_NAME FROM information_schema.schemata WHERE SCHEMA_NAME='$1';" 2>/dev/null)" ]
}

for db in "$SRC" "$REF"; do
    if ! have_db "$db"; then
        echo "error: database '$db' does not exist." >&2
        [ "$db" = "$SRC" ] && echo "       Import it first: dev/scripts/import-live-dump.sh ~/backups/live_db.sql" >&2
        [ "$db" = "$REF" ] && echo "       The 1.8.0 reference install is missing - see docs/project/05-local-development-setup.md" >&2
        exit 1
    fi
done

mkdir -p "$OUT_DIR"

echo "== Lush View Hotel: ${SRC} (1.7.0) -> ${DST} (1.8.0), reference ${REF}"
echo "   ${SRC} is read only. ${DST} will be dropped and rebuilt."
echo

# --------------------------------------------------------------------------
# 1. Copy the 1.7.0 data
# --------------------------------------------------------------------------
CHARSET="$(sql -e "SELECT DEFAULT_CHARACTER_SET_NAME FROM information_schema.schemata WHERE SCHEMA_NAME='${SRC}';")"
COLLATE="$(sql -e "SELECT DEFAULT_COLLATION_NAME FROM information_schema.schemata WHERE SCHEMA_NAME='${SRC}';")"
CHARSET="${CHARSET:-utf8mb3}"
COLLATE="${COLLATE:-utf8_general_ci}"

echo "-- 1/4 copying ${SRC} into ${DST} (charset ${CHARSET}/${COLLATE})"
"$ENG" exec -i qlo-db sh -c "
    mariadb -u'${ROOT_USER}' -p'${ROOT_PASS}' -e \"DROP DATABASE IF EXISTS \\\`${DST}\\\`; CREATE DATABASE \\\`${DST}\\\` CHARACTER SET ${CHARSET} COLLATE ${COLLATE};\" \
    && mariadb-dump -u'${ROOT_USER}' -p'${ROOT_PASS}' --single-transaction --routines --triggers --default-character-set='${CHARSET}' '${SRC}' \
       | mariadb -u'${ROOT_USER}' -p'${ROOT_PASS}' --default-character-set='${CHARSET}' '${DST}'
"
TABLES_BEFORE="$(sql -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DST}' AND table_type='BASE TABLE';")"
echo "      ${TABLES_BEFORE} tables copied."

# The application connects as its own user, not as root, and a database created
# by root is not automatically reachable by it. Without this GRANT the switch
# succeeds and then every page fails with "access denied" - the same trap
# import-live-dump.sh already handles for qloapps_live.
APP_USER="${QLO_APP_USER:-qlo}"
if sql -e "GRANT ALL PRIVILEGES ON \`${DST}\`.* TO '${APP_USER}'@'%'; FLUSH PRIVILEGES;" >/dev/null 2>&1; then
    echo "      granted ${APP_USER} access to ${DST}."
else
    echo "      WARNING: could not GRANT on ${DST} to '${APP_USER}'@'%'." >&2
    echo "               If the site reports access denied, run this as root:" >&2
    echo "               GRANT ALL PRIVILEGES ON \`${DST}\`.* TO '${APP_USER}'@'%'; FLUSH PRIVILEGES;" >&2
fi

# --------------------------------------------------------------------------
# 2. Tables 1.8.0 has and the copy does not
# --------------------------------------------------------------------------
mapfile -t MISSING_TABLES < <(sql -e "
    SELECT t.TABLE_NAME
    FROM information_schema.TABLES t
    WHERE t.TABLE_SCHEMA='${REF}' AND t.TABLE_TYPE='BASE TABLE'
      AND NOT EXISTS (
          SELECT 1 FROM information_schema.TABLES x
          WHERE x.TABLE_SCHEMA='${DST}' AND x.TABLE_NAME=t.TABLE_NAME)
    ORDER BY t.TABLE_NAME;")

echo "-- 2/4 missing tables: ${#MISSING_TABLES[@]}"

# --------------------------------------------------------------------------
# 3. Columns 1.8.0 has and the copy does not
# --------------------------------------------------------------------------
# The definition is rebuilt from information_schema rather than parsed out of a
# SHOW CREATE TABLE, so every column gets 1.8.0's exact type, nullability,
# default, extra and comment. CURRENT_TIMESTAMP is emitted unquoted (quoting it
# would store the literal string), and AUTO_INCREMENT / ON UPDATE are carried
# over from `extra`.
ALTERS_FILE="$(mktemp)"
sql -e "
    SELECT CONCAT(
        'ALTER TABLE \`', c.TABLE_NAME, '\` ADD COLUMN \`', c.COLUMN_NAME, '\` ', c.COLUMN_TYPE,
        IF(c.IS_NULLABLE='NO', ' NOT NULL', ' NULL'),
        CASE
            WHEN c.COLUMN_DEFAULT IS NULL THEN IF(c.IS_NULLABLE='NO', '', ' DEFAULT NULL')
            WHEN UPPER(c.COLUMN_DEFAULT)='CURRENT_TIMESTAMP' THEN ' DEFAULT CURRENT_TIMESTAMP'
            ELSE CONCAT(' DEFAULT ', QUOTE(c.COLUMN_DEFAULT))
        END,
        CASE
            WHEN c.EXTRA LIKE '%auto_increment%' THEN ' AUTO_INCREMENT'
            WHEN c.EXTRA LIKE '%on update%' THEN ' ON UPDATE CURRENT_TIMESTAMP'
            ELSE ''
        END,
        IF(c.COLUMN_COMMENT='', '', CONCAT(' COMMENT ', QUOTE(c.COLUMN_COMMENT))),
        ';')
    FROM information_schema.COLUMNS c
    WHERE c.TABLE_SCHEMA='${REF}'
      AND EXISTS (SELECT 1 FROM information_schema.TABLES x
                  WHERE x.TABLE_SCHEMA='${DST}' AND x.TABLE_NAME=c.TABLE_NAME)
      AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS y
                      WHERE y.TABLE_SCHEMA='${DST}' AND y.TABLE_NAME=c.TABLE_NAME
                        AND y.COLUMN_NAME=c.COLUMN_NAME)
    ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION;" > "$ALTERS_FILE"
ALTER_COUNT="$(grep -c . "$ALTERS_FILE" || true)"
echo "-- 3/4 missing columns: ${ALTER_COUNT}"

# --------------------------------------------------------------------------
# 4. Type differences: reported, never changed
# --------------------------------------------------------------------------
MISMATCH_FILE="$(mktemp)"
sql -e "
    SELECT CONCAT('-- DIFFERS  ', r.TABLE_NAME, '.', r.COLUMN_NAME, ': 1.8.0 ',
                  r.COLUMN_TYPE, '  vs  copy ', d.COLUMN_TYPE)
    FROM information_schema.COLUMNS r
    JOIN information_schema.COLUMNS d
      ON d.TABLE_SCHEMA='${DST}' AND d.TABLE_NAME=r.TABLE_NAME AND d.COLUMN_NAME=r.COLUMN_NAME
    WHERE r.TABLE_SCHEMA='${REF}' AND r.COLUMN_TYPE <> d.COLUMN_TYPE
    ORDER BY r.TABLE_NAME, r.ORDINAL_POSITION;" > "$MISMATCH_FILE"
MISMATCH_COUNT="$(grep -c . "$MISMATCH_FILE" || true)"
echo "-- 4/4 columns whose type differs: ${MISMATCH_COUNT} (left untouched, listed in the output file)"

# --------------------------------------------------------------------------
# Assemble the migration file
# --------------------------------------------------------------------------
{
    echo "-- Generated by dev/scripts/upgrade-live-copy.sh on $(date -u '+%Y-%m-%d %H:%M:%S') UTC"
    echo "-- ${SRC} (1.7.0 production copy) -> ${DST}, schema reference ${REF} (1.8.0)."
    echo "-- Derived from the production schema. Gitignored on purpose: do not commit."
    echo
    echo "-- sql_mode is relaxed for the duration so that adding a NOT NULL column to a"
    echo "-- table that already holds rows cannot abort the whole migration."
    echo "SET @old_sql_mode = @@SESSION.sql_mode;"
    echo "SET SESSION sql_mode = '';"
    echo
    if [ "$MISMATCH_COUNT" -gt 0 ]; then
        echo "-- ============ type differences, NOT changed ============"
        cat "$MISMATCH_FILE"
        echo
    fi
    if [ "${#MISSING_TABLES[@]}" -gt 0 ]; then
        echo "-- ============ ${#MISSING_TABLES[@]} tables new in 1.8.0, with reference rows ============"
    fi
} > "$OUT_FILE"

# Table structure AND the rows 1.8.0 expects to find there, straight from the
# reference install. Streamed through the container so the dump never has to be
# written to the host twice.
if [ "${#MISSING_TABLES[@]}" -gt 0 ]; then
    # --skip-extended-insert puts one INSERT per row instead of one huge
    # multi-row statement, so a single badly escaped value costs one row and
    # names itself in the error, rather than poisoning a whole table's data.
    # --hex-blob removes another class of escaping problem entirely.
    "$ENG" exec -i qlo-db sh -c "
        mariadb-dump -u'${ROOT_USER}' -p'${ROOT_PASS}' --single-transaction --skip-add-drop-table \
            --skip-extended-insert --hex-blob --no-tablespaces \
            --default-character-set='${CHARSET}' '${REF}' ${MISSING_TABLES[*]}
    " >> "$OUT_FILE"
fi

if [ "$ALTER_COUNT" -gt 0 ]; then
    {
        echo
        echo "-- ============ ${ALTER_COUNT} columns new in 1.8.0 ============"
        cat "$ALTERS_FILE"
    } >> "$OUT_FILE"
fi

echo "SET SESSION sql_mode = @old_sql_mode;" >> "$OUT_FILE"
rm -f "$ALTERS_FILE" "$MISMATCH_FILE"

# --------------------------------------------------------------------------
# Apply
# --------------------------------------------------------------------------
echo
echo "Applying ${OUT_FILE} to ${DST} ..."
ERR_FILE="${OUT_DIR}/apply-errors.log"
: > "$ERR_FILE"

# --force is not optional here. On the first run the client hit one statement it
# could not parse ("ERROR at line 1190: Unknown command '\\'") and stopped dead,
# which silently threw away every ALTER TABLE after it - all 31 new columns. The
# site still booted because the dashboard does not query those columns, so the
# damage was invisible. With --force every applicable statement is applied and
# each failure is reported, then verified by the counts below.
set +e
"$ENG" exec -i qlo-db sh -c "mariadb -u'${ROOT_USER}' -p'${ROOT_PASS}' --force --default-character-set='${CHARSET}' '${DST}'" < "$OUT_FILE" 2> "$ERR_FILE"
set -e

grep -v "Using a password on the command line" "$ERR_FILE" > "${ERR_FILE}.tmp" 2>/dev/null || true
mv -f "${ERR_FILE}.tmp" "$ERR_FILE"
if [ -s "$ERR_FILE" ]; then
    ERR_COUNT="$(grep -c . "$ERR_FILE" || true)"
    echo
    echo "!! ${ERR_COUNT} statement(s) failed while applying - the rest were applied anyway."
    head -20 "$ERR_FILE"
    echo "!! full list: ${ERR_FILE}"
else
    rm -f "$ERR_FILE"
fi

TABLES_AFTER="$(sql -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DST}' AND table_type='BASE TABLE';")"
STILL_MISSING="$(sql -e "
    SELECT COUNT(*) FROM information_schema.TABLES t
    WHERE t.TABLE_SCHEMA='${REF}' AND t.TABLE_TYPE='BASE TABLE'
      AND NOT EXISTS (SELECT 1 FROM information_schema.TABLES x
                      WHERE x.TABLE_SCHEMA='${DST}' AND x.TABLE_NAME=t.TABLE_NAME);")"
COLS_STILL_MISSING="$(sql -e "
    SELECT COUNT(*) FROM information_schema.COLUMNS c
    WHERE c.TABLE_SCHEMA='${REF}'
      AND EXISTS (SELECT 1 FROM information_schema.TABLES x
                  WHERE x.TABLE_SCHEMA='${DST}' AND x.TABLE_NAME=c.TABLE_NAME)
      AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS y
                      WHERE y.TABLE_SCHEMA='${DST}' AND y.TABLE_NAME=c.TABLE_NAME
                        AND y.COLUMN_NAME=c.COLUMN_NAME);")"

echo
echo "== done."
echo "   ${DST}: ${TABLES_BEFORE} -> ${TABLES_AFTER} tables (+$((TABLES_AFTER - TABLES_BEFORE)))"
echo "   tables still missing vs 1.8.0 : ${STILL_MISSING}   (want 0)"
echo "   columns still missing vs 1.8.0: ${COLS_STILL_MISSING}   (want 0)"
echo "   type differences left alone   : ${MISMATCH_COUNT} (grep '^-- DIFFERS' ${OUT_FILE})"
echo
echo "Next:"
echo "   dev/scripts/use-db.sh 18"
echo "   podman-compose exec app php dev/scripts/set-admin-password.php you@example.com 'YourLocalPassword'"
echo "       (${DST} was rebuilt from the 1.7.0 copy, so any password you set"
echo "        on a previous run is gone and must be set again.)"
echo "   then open http://localhost:8080/admin-dev/ and send me any error with:"
echo "   podman-compose logs app | tail -40"

if [ "$STILL_MISSING" != "0" ] || [ "$COLS_STILL_MISSING" != "0" ]; then
    echo
    echo "WARNING: the schema is still incomplete. Send me the failing lines from"
    echo "         ${ERR_FILE} - do not trust the data until both counts are 0."
    exit 2
fi
