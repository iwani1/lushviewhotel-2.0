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
#   1. copies qloapps_live into qloapps_18 (drop and recreate, same charset) and
#      applies the approved hand-written widening migrations (001 password
#      columns, 003 payment amount columns) to qloapps_18 - leaving qloapps_live
#      untouched
#   2. finds every table that exists in 1.8.0 but not in the copy, creating
#      lookup tables WITH their reference rows from qloapps_fresh and dumping
#      non-vocabulary/module-ID tables structure-only (--no-data)
#   3. finds every column that exists in 1.8.0 but not in the copy, and adds it
#      with 1.8.0's exact definition
#   4. reports any remaining columns whose type still differs between the two
#      versions, without changing them automatically
#   5. writes the derived delta to dev/migrations/generated/ so it can be
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

# Apply the committed, owner-approved column-widening migrations to ${DST}
# before diffing against ${REF} (same order as production runbook steps 3.3 and
# 3.3b: 001 -> 003 -> 002). ${SRC} is never written to, so you do not have to
# re-import ${SRC} when a hand-written migration is added or extended.
for mig in \
    "dev/migrations/001_widen_password_columns.up.sql" \
    "dev/migrations/003_widen_payment_amounts.up.sql"
do
    if [ ! -f "$mig" ]; then
        echo "error: required migration missing: $mig" >&2
        exit 1
    fi
    "$ENG" exec -i qlo-db sh -c "mariadb -u'${ROOT_USER}' -p'${ROOT_PASS}' --default-character-set='${CHARSET}' '${DST}'" < "$mig"
    echo "      applied ${mig} to ${DST}."
done

# --------------------------------------------------------------------------
# Defer tables and columns owned by UNINSTALLED 1.8.0 modules
# --------------------------------------------------------------------------
# Why this matters (caught 2026-11-08 while rehearsing step 6.1):
# qloapps_fresh installs qlocmconnector, qlogooglehotelconnector and
# qlocrontaskmanager during setup, whereas a 1.7.0 database has none of them in
# qlo_module. Both QcmcCMConnectorDb::createTables() and QghcDb::createTables()
# execute UNGUARDED `ALTER TABLE ... ADD COLUMN` statements on install(), and
# PHP 8's PDO defaults to ERRMODE_EXCEPTION - so pre-adding their 8 columns
# here makes clicking "Install" in the back office crash halfway through with
# `SQLSTATE[42S21]: 1060 Duplicate column name`, leaving the module
# half-installed in qlo_module (and pre-creating qctm_cron_task leaks
# qloapps_fresh's id_module numbers). None of those 5 tables or 8 columns is
# queried outside its owning module, so each uninstalled module must create its
# own schema when installed.
DB_PREFIX="$(sql -e "
    SELECT LEFT(TABLE_NAME, CHAR_LENGTH(TABLE_NAME) - 6)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA='${DST}' AND TABLE_NAME LIKE '%\\_module'
    ORDER BY CHAR_LENGTH(TABLE_NAME) ASC LIMIT 1;")"
DB_PREFIX="${DB_PREFIX:-qlo_}"

mod_installed() {
    [ "0" != "$(sql -e "SELECT COUNT(*) FROM \`${DST}\`.\`${DB_PREFIX}module\` WHERE name='$1';")" ]
}

EXCL_TABLES_LIST=()
EXCL_COLS_LIST=()
EXCL_TABLES_SQL="1=1"
EXCL_COLS_SQL="1=1"

if ! mod_installed "qlocmconnector"; then
    EXCL_TABLES_LIST+=("${DB_PREFIX}ari_updates")
    EXCL_COLS_LIST+=("${DB_PREFIX}orders.id_channel_manager_booking" "${DB_PREFIX}htl_booking_detail.rate_plan_name")
    EXCL_TABLES_SQL="${EXCL_TABLES_SQL} AND t.TABLE_NAME <> '${DB_PREFIX}ari_updates'"
    EXCL_COLS_SQL="${EXCL_COLS_SQL}
      AND NOT (c.TABLE_NAME='${DB_PREFIX}orders' AND c.COLUMN_NAME='id_channel_manager_booking')
      AND NOT (c.TABLE_NAME='${DB_PREFIX}htl_booking_detail' AND c.COLUMN_NAME='rate_plan_name')"
fi

if ! mod_installed "qlogooglehotelconnector"; then
    EXCL_TABLES_LIST+=("${DB_PREFIX}qghc_api_log" "${DB_PREFIX}qghc_ari_updates")
    EXCL_COLS_LIST+=(
        "${DB_PREFIX}htl_branch_info.is_google_hotel_enabled"
        "${DB_PREFIX}htl_branch_info.id_google_hotel"
        "${DB_PREFIX}htl_branch_info.google_status"
        "${DB_PREFIX}htl_branch_info.failure_reason"
        "${DB_PREFIX}htl_room_type.is_google_hotel_enabled"
        "${DB_PREFIX}htl_room_type.id_google_room_type"
    )
    EXCL_TABLES_SQL="${EXCL_TABLES_SQL} AND t.TABLE_NAME NOT IN ('${DB_PREFIX}qghc_api_log','${DB_PREFIX}qghc_ari_updates')"
    EXCL_COLS_SQL="${EXCL_COLS_SQL}
      AND NOT (c.TABLE_NAME='${DB_PREFIX}htl_branch_info'
               AND c.COLUMN_NAME IN ('is_google_hotel_enabled','id_google_hotel','google_status','failure_reason'))
      AND NOT (c.TABLE_NAME='${DB_PREFIX}htl_room_type'
               AND c.COLUMN_NAME IN ('is_google_hotel_enabled','id_google_room_type'))"
fi

if ! mod_installed "qlocrontaskmanager"; then
    EXCL_TABLES_LIST+=("${DB_PREFIX}qctm_cron_task" "${DB_PREFIX}qctm_cron_task_log")
    EXCL_TABLES_SQL="${EXCL_TABLES_SQL} AND t.TABLE_NAME NOT IN ('${DB_PREFIX}qctm_cron_task','${DB_PREFIX}qctm_cron_task_log')"
fi

# --------------------------------------------------------------------------
# 2. Tables 1.8.0 has and the copy does not
# --------------------------------------------------------------------------
mapfile -t MISSING_TABLES < <(sql -e "
    SELECT t.TABLE_NAME
    FROM information_schema.TABLES t
    WHERE t.TABLE_SCHEMA='${REF}' AND t.TABLE_TYPE='BASE TABLE'
      AND (${EXCL_TABLES_SQL})
      AND NOT EXISTS (
          SELECT 1 FROM information_schema.TABLES x
          WHERE x.TABLE_SCHEMA='${DST}' AND x.TABLE_NAME=t.TABLE_NAME)
    ORDER BY t.TABLE_NAME;")

echo "-- 2/4 missing core/installed-module tables: ${#MISSING_TABLES[@]} (deferred to uninstalled 1.8.0 modules: ${#EXCL_TABLES_LIST[@]})"

# --------------------------------------------------------------------------
# 3. Columns 1.8.0 has and the copy does not
# --------------------------------------------------------------------------
# The definition is rebuilt from information_schema rather than parsed out of a
# SHOW CREATE TABLE, so every column gets 1.8.0's exact type, nullability,
# default, extra and comment.
#
# COLUMN_DEFAULT needs care, and getting it wrong costs the whole migration:
# MariaDB 10.2+ returns the default as a literal EXPRESSION, so a varchar
# defaulting to image arrives as 'image' quotes included, and a default of NULL
# arrives as the four-character string NULL rather than SQL NULL. Wrapping those
# in QUOTE() produced DEFAULT '\'image\'' and DEFAULT 'NULL', which the client
# rejected with "Unknown command" and error 1067. So: a value that already opens
# with a quote (CHAR(39)) is emitted verbatim, the string NULL becomes an
# unquoted DEFAULT NULL, numeric types and CURRENT_TIMESTAMP are emitted
# verbatim, and only a genuinely bare string is quoted. That covers MariaDB and
# MySQL, which return the unquoted form.
ALTERS_FILE="$(mktemp)"
sql -e "
    SELECT CONCAT(
        'ALTER TABLE \`', c.TABLE_NAME, '\` ADD COLUMN \`', c.COLUMN_NAME, '\` ', c.COLUMN_TYPE,
        IF(c.IS_NULLABLE='NO', ' NOT NULL', ' NULL'),
        CASE
            WHEN c.COLUMN_DEFAULT IS NULL
                THEN IF(c.IS_NULLABLE='NO', '', ' DEFAULT NULL')
            WHEN UPPER(c.COLUMN_DEFAULT)='NULL'
                THEN ' DEFAULT NULL'
            WHEN LEFT(c.COLUMN_DEFAULT, 1) = CHAR(39)
                THEN CONCAT(' DEFAULT ', c.COLUMN_DEFAULT)
            WHEN UPPER(c.COLUMN_DEFAULT) LIKE 'CURRENT_TIMESTAMP%'
                THEN CONCAT(' DEFAULT ', c.COLUMN_DEFAULT)
            WHEN UPPER(c.COLUMN_DEFAULT) IN ('TRUE','FALSE')
                THEN CONCAT(' DEFAULT ', c.COLUMN_DEFAULT)
            WHEN c.DATA_TYPE IN ('tinyint','smallint','mediumint','int','bigint',
                                 'decimal','float','double','bit')
                THEN CONCAT(' DEFAULT ', c.COLUMN_DEFAULT)
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
      AND (${EXCL_COLS_SQL})
      AND EXISTS (SELECT 1 FROM information_schema.TABLES x
                  WHERE x.TABLE_SCHEMA='${DST}' AND x.TABLE_NAME=c.TABLE_NAME)
      AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS y
                      WHERE y.TABLE_SCHEMA='${DST}' AND y.TABLE_NAME=c.TABLE_NAME
                        AND y.COLUMN_NAME=c.COLUMN_NAME)
    ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION;" > "$ALTERS_FILE"
ALTER_COUNT="$(grep -c . "$ALTERS_FILE" || true)"
echo "-- 3/4 missing core/installed-module columns: ${ALTER_COUNT} (deferred to uninstalled 1.8.0 modules: ${#EXCL_COLS_LIST[@]})"

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
    if [ "${#EXCL_TABLES_LIST[@]}" -gt 0 ] || [ "${#EXCL_COLS_LIST[@]}" -gt 0 ]; then
        echo "-- ============ deferred to uninstalled 1.8.0 modules ============"
        echo "-- Left out on purpose so each module's own install() creates them without"
        echo "-- throwing SQLSTATE[42S21] 1060 Duplicate column name on unguarded ALTERs:"
        [ "${#EXCL_TABLES_LIST[@]}" -gt 0 ] && echo "--   tables : ${EXCL_TABLES_LIST[*]}"
        [ "${#EXCL_COLS_LIST[@]}" -gt 0 ] && echo "--   columns: ${EXCL_COLS_LIST[*]}"
        echo
    fi
    if [ "${#MISSING_TABLES[@]}" -gt 0 ]; then
        echo "-- ============ ${#MISSING_TABLES[@]} core/installed-module tables new in 1.8.0 ============"
    fi
} > "$OUT_FILE"

# Table structure AND the reference vocabulary rows 1.8.0 expects (booking
# statuses, sources, selling objects, property types, common amenity definitions
# and so on), straight from the reference install.
#
# Tables whose rows in ${REF} hold ${REF}-specific foreign IDs - qctm_cron_task
# (bound to qloapps_fresh's id_module numbers, which differ on live), demo
# branch/room-type amenity assignments (saveDummyHotelAmenities /
# saveDummyProductsAndRelatedInfo), and transactional/log tables - are dumped
# structure-only (--no-data) so foreign IDs from the reference install never
# leak into live data.
if [ "${#MISSING_TABLES[@]}" -gt 0 ]; then
    SEED_TABLES=()
    STRUCT_ONLY_TABLES=()
    for tbl in "${MISSING_TABLES[@]}"; do
        case "$tbl" in
            *_qctm_cron_task|*_qctm_cron_task_log|\
            *_htl_branch_amenity|*_htl_room_type_amenity|*_htl_connected_room|\
            *_htl_booking_status_history|*_cart_rule_hotel|\
            *_order_tax_detail|*_order_tax_exemption|\
            *_ari_updates|*_qghc_api_log|*_qghc_ari_updates)
                STRUCT_ONLY_TABLES+=("$tbl")
                ;;
            *)
                SEED_TABLES+=("$tbl")
                ;;
        esac
    done

    # --skip-extended-insert puts one INSERT per row instead of one huge
    # multi-row statement, so a single badly escaped value costs one row and
    # names itself in the error, rather than poisoning a whole table's data.
    # --hex-blob removes another class of escaping problem entirely.
    if [ "${#SEED_TABLES[@]}" -gt 0 ]; then
        "$ENG" exec -i qlo-db sh -c "
            mariadb-dump -u'${ROOT_USER}' -p'${ROOT_PASS}' --single-transaction --skip-add-drop-table \
                --skip-extended-insert --hex-blob --no-tablespaces \
                --default-character-set='${CHARSET}' '${REF}' ${SEED_TABLES[*]}
        " >> "$OUT_FILE"
    fi
    if [ "${#STRUCT_ONLY_TABLES[@]}" -gt 0 ]; then
        echo "-- structure-only tables (rows in ${REF} carry ${REF}-local IDs): ${STRUCT_ONLY_TABLES[*]}" >> "$OUT_FILE"
        "$ENG" exec -i qlo-db sh -c "
            mariadb-dump -u'${ROOT_USER}' -p'${ROOT_PASS}' --single-transaction --skip-add-drop-table \
                --no-data --no-tablespaces \
                --default-character-set='${CHARSET}' '${REF}' ${STRUCT_ONLY_TABLES[*]}
        " >> "$OUT_FILE"
    fi
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
    ERR_COUNT="$(grep -c '^ERROR' "$ERR_FILE" || true)"
    echo
    echo "!! ${ERR_COUNT} error(s) while applying - every other statement was applied anyway."
    head -20 "$ERR_FILE"
    echo "!! full list: ${ERR_FILE}"
else
    rm -f "$ERR_FILE"
fi

TABLES_AFTER="$(sql -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DST}' AND table_type='BASE TABLE';")"
STILL_MISSING="$(sql -e "
    SELECT COUNT(*) FROM information_schema.TABLES t
    WHERE t.TABLE_SCHEMA='${REF}' AND t.TABLE_TYPE='BASE TABLE'
      AND (${EXCL_TABLES_SQL})
      AND NOT EXISTS (SELECT 1 FROM information_schema.TABLES x
                      WHERE x.TABLE_SCHEMA='${DST}' AND x.TABLE_NAME=t.TABLE_NAME);")"
COLS_STILL_MISSING="$(sql -e "
    SELECT COUNT(*) FROM information_schema.COLUMNS c
    WHERE c.TABLE_SCHEMA='${REF}'
      AND (${EXCL_COLS_SQL})
      AND EXISTS (SELECT 1 FROM information_schema.TABLES x
                  WHERE x.TABLE_SCHEMA='${DST}' AND x.TABLE_NAME=c.TABLE_NAME)
      AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS y
                      WHERE y.TABLE_SCHEMA='${DST}' AND y.TABLE_NAME=c.TABLE_NAME
                        AND y.COLUMN_NAME=c.COLUMN_NAME);")"

echo
echo "== done."
echo "   ${DST}: ${TABLES_BEFORE} -> ${TABLES_AFTER} tables (+$((TABLES_AFTER - TABLES_BEFORE)); ${#EXCL_TABLES_LIST[@]} deferred to uninstalled 1.8.0 modules)"
echo "   tables still missing vs 1.8.0 : ${STILL_MISSING}   (want 0; excludes ${#EXCL_TABLES_LIST[@]} module-owned)"
echo "   columns still missing vs 1.8.0: ${COLS_STILL_MISSING}   (want 0; excludes ${#EXCL_COLS_LIST[@]} module-owned)"
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
