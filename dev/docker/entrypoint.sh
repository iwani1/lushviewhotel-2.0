#!/bin/sh
# Container entrypoint for local development.
#
# Three jobs before handing over to Apache:
#   1. wait for MariaDB to accept connections
#   2. work out which user Apache must run as, given the container runtime
#   3. make the directories QloApps writes to owned by that user
#
# ---------------------------------------------------------------------------
# 1. WHY THE DATABASE WAIT LIVES HERE
#
# The compose file used to express this as
#   depends_on: { db: { condition: service_healthy } }
# which is the correct Docker idiom, but podman-compose has supported the
# long-form "condition" key inconsistently across versions and silently ignores
# it in some. Waiting here means the same compose file behaves identically under
# Docker, Podman and podman-compose.
#
# It also matters on the merits: MariaDB restarts itself once during first
# initialisation to apply grants, so "container started" is not the same as
# "ready to accept queries". Running the installer too early fails with a
# connection error that looks like wrong credentials.
#
# ---------------------------------------------------------------------------
# 2. WHY THE APACHE USER IS DETECTED RATHER THAN HARDCODED
#
# The repository is bind-mounted from the host, so files are owned by your host
# user, and Apache cannot write to them as www-data. The installer explicitly
# checks recursive write permission on twelve paths
# (install-dev/controllers/http/system.php) and refuses to continue without it.
#
# The naive fix - chown those paths to www-data - is correct under Docker but
# HARMFUL under rootless Podman, because of how user namespaces map:
#
#     Docker (rootful)     container www-data (uid 33) -> host uid 33
#     Podman (rootless)    container root     (uid 0)  -> YOUR host user
#                          container www-data (uid 33) -> a host SUBUID
#                                                           (e.g. 100032)
#
# So under rootless Podman, chowning modules/ or img/ to www-data would transfer
# those tracked source files on the host to an unmapped subuid, and you would no
# longer be able to edit your own code or run git cleanly against it.
#
# Under rootless Podman the right answer is the opposite: run Apache as
# container root, which IS your host user, so everything written lands on the
# host owned by you and no chown is needed at all.
#
# .git is never chowned under any runtime - changing its ownership makes git
# report "dubious ownership" on the host.
set -e

WRITABLE_DIRS="
config
cache
log
img
mails
modules
translations
upload
download
themes/hotel-reservation-theme/lang
themes/hotel-reservation-theme/pdf/lang
themes/hotel-reservation-theme/cache
"

DB_HOST="${QLO_DB_SERVER:-db}"
DB_PORT="${QLO_DB_PORT:-3306}"

# --- 1. wait for the database -------------------------------------------------
# Probes with PHP's own socket call rather than a mysql client: PHP is
# guaranteed present in this image, and a bare TCP connect needs no credentials.
# These are exported under the exact names the one-liner reads via getenv();
# without the export getenv() returns false and the probe spins to the timeout.
QLO_DB_HOST="$DB_HOST"
QLO_DB_PORT="$DB_PORT"
export QLO_DB_HOST QLO_DB_PORT

echo "[qlo-entrypoint] waiting for MariaDB at ${DB_HOST}:${DB_PORT} ..."
i=0
until php -r '$c=@fsockopen(getenv("QLO_DB_HOST"),(int)getenv("QLO_DB_PORT"),$e,$s,2);exit($c?0:1);' \
      >/dev/null 2>&1
do
    i=$((i + 1))
    if [ "$i" -ge 90 ]; then
        echo "[qlo-entrypoint] WARNING: database still unreachable after 90s." >&2
        echo "[qlo-entrypoint]          Starting Apache anyway. If the installer" >&2
        echo "[qlo-entrypoint]          cannot connect, check:  compose logs db" >&2
        break
    fi
    sleep 1
done
if [ "$i" -lt 90 ]; then
    echo "[qlo-entrypoint] database is accepting connections (waited ${i}s)."
fi

# --- 2. detect rootless Podman ------------------------------------------------
# Podman writes /run/.containerenv inside every container, and records whether
# the engine is rootless. Docker does not create this file, so its presence is a
# reliable runtime marker.
ROOTLESS_PODMAN=0
if [ -f /run/.containerenv ] && grep -q '^rootless=1' /run/.containerenv 2>/dev/null; then
    ROOTLESS_PODMAN=1
fi

if [ "$ROOTLESS_PODMAN" = "1" ]; then
    # Apache's User/Group directives accept the "#uid" form. Container uid 0 is
    # the host user under rootless Podman, so files written stay yours.
    APACHE_RUN_USER="#0"
    APACHE_RUN_GROUP="#0"
    export APACHE_RUN_USER APACHE_RUN_GROUP
    echo "[qlo-entrypoint] rootless Podman detected - requesting Apache run as"
    echo "[qlo-entrypoint] container root (which IS your host user), so repository"
    echo "[qlo-entrypoint] files keep your ownership and no chown is needed."
else
    echo "[qlo-entrypoint] Docker (or rootful Podman) - Apache runs as www-data."
    # --- 3. fix ownership on the writable paths ------------------------------
    for d in $WRITABLE_DIRS; do
        if [ -d "/var/www/html/$d" ]; then
            chown -R www-data:www-data "/var/www/html/$d" 2>/dev/null || true
        fi
    done
    # The installer also writes the root .htaccess and config/settings.inc.php,
    # both of which live in the document root itself.
    chown www-data:www-data /var/www/html 2>/dev/null || true
fi

# --- 4. report what Apache will actually run as ------------------------------
# apache2-foreground sources /etc/apache2/envvars, which is the last word on the
# Apache user. Resolving it here in a subshell tells us whether the override we
# exported actually survived - the Dockerfile patches envvars to honour it, but
# if that patch ever stops matching, this line shows www-data instead of #0 and
# the rootless-Podman ownership problem is back. Worth reading on first start.
_resolved="$( ( . /etc/apache2/envvars >/dev/null 2>&1; echo "$APACHE_RUN_USER" ) 2>/dev/null )"
echo "[qlo-entrypoint] /etc/apache2/envvars resolves APACHE_RUN_USER to: ${_resolved:-<unknown>}"
if [ "$ROOTLESS_PODMAN" = "1" ] && [ "$_resolved" != "#0" ]; then
    echo "[qlo-entrypoint] WARNING: expected '#0' under rootless Podman but got" >&2
    echo "[qlo-entrypoint]          '${_resolved}'. Repository files may end up owned" >&2
    echo "[qlo-entrypoint]          by a host subuid. Fall back to Docker, or run" >&2
    echo "[qlo-entrypoint]          'sudo chown -R \$UID:\$UID .' on the host afterwards." >&2
fi

echo "[qlo-entrypoint] PHP $(php -r 'echo PHP_VERSION;') / Apache ready."
echo "[qlo-entrypoint] Database host is '${DB_HOST}' inside this network - not localhost."

exec docker-php-entrypoint "$@"
