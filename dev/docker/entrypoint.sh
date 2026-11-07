#!/bin/sh
# Entrypoint for the Lush View Hotel local development container.
#
# Does three things before handing over to Apache:
#   1. Waits for MariaDB to accept TCP connections.
#   2. Works out which uid/gid Apache should run as, and makes the writable
#      directories usable by it.
#   3. Reports what it decided, so a failure is diagnosable from the log.
#
# ---------------------------------------------------------------------------
# WHY THIS IS NOT JUST "chown -R www-data"
# ---------------------------------------------------------------------------
# The application is bind-mounted from the host, so anything this script does to
# ownership or permissions happens to the real working copy.
#
# Naively chowning the tree to www-data is actively harmful:
#   * Under rootful Docker, container uid 33 is host uid 33, so the user's clone
#     ends up owned by a uid that is not them. Chowning the repository ROOT would
#     leave them unable to create files in their own clone.
#   * Under rootless Podman, uid 33 maps into the user's subuid range
#     (/etc/subuid, e.g. 100032), so git sees every tracked file as
#     modified-by-owner.
#
# So this script never chowns the bind mount. Preferred plan: run Apache as the
# uid that ALREADY owns the mount, so the process is the owner and nothing needs
# changing at all. Fallback (only when that uid is 0, which Apache refuses):
# run Apache as www-data with the mount's GROUP, and add group-write. Ownership
# on the host is left exactly as it was either way, and git ignores group bits,
# so `git status` stays clean.
#
# ---------------------------------------------------------------------------
# HARD CONSTRAINTS VERIFIED IN THE APACHE httpd SOURCE
# ---------------------------------------------------------------------------
# From modules/arch/unix/mod_unixd.c, unixd_set_user():
#
#     #if !defined (BIG_SECURITY_HOLE) && !defined (OS2)
#         if (ap_unixd_config.user_id == 0) {
#             return "Error: Apache has not been designed to serve pages
#                     while running as root. ...";
#         }
#     #endif
#
# => Apache REFUSES to start if User resolves to uid 0. The Debian package is
#    not built with -DBIG_SECURITY_HOLE. This rules out the otherwise tempting
#    "run Apache as container root" trick under rootless Podman, where container
#    root IS the host user. (An earlier revision of this file did exactly that;
#    it would have failed to boot.)
#
# From the same file, set_group_privs():
#
#     if (ap_unixd_config.user_name[0] == '#') {
#         uid_t uid = atol(&ap_unixd_config.user_name[1]);
#         if ((ent = getpwuid(uid)) == NULL) {
#             ap_log_error(... APLOGNO(02155) "getpwuid: couldn't determine
#                 user name from uid %ld ...");
#             return -1;
#         }
#     }
#
# => A numeric "#uid" User must have an /etc/passwd entry or Apache aborts. So
#    we cannot simply adopt the host uid without registering it first - which is
#    what ensure_user() below does.
#
# unixd_set_group() has NO equivalent uid-0 check, so the group may freely be
# gid 0 ("root") or any numeric gid. That is what makes the fallback viable.
#
# Finally, apache2-foreground (docker-library/php) does `mkdir -p` on every
# APACHE_*_DIR but never chowns them. /var/log/apache2 ships as root:www-data
# mode 750, so once Apache runs as a different identity those internal
# directories must be fixed up here or it cannot write its error log and PID
# file. They live inside the container image, never on a bind mount, so
# adjusting them has no effect on the host.
set -eu

APP_ROOT=/var/www/html

# Paths the installer must be able to write to. Taken from the literal checks in
# install-dev/controllers/http/system.php:88-103 (plus the two theme cache dirs
# Smarty populates at runtime). Deliberately NOT the whole tree, and never .git -
# changing the owner of git internals corrupts the repository.
QLO_WRITABLE_PATHS="
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

log() { echo "[qlo-entrypoint] $*"; }
warn() { echo "[qlo-entrypoint] $*" >&2; }

# ---------------------------------------------------------------------------
# 1. Wait for MariaDB
# ---------------------------------------------------------------------------
# `depends_on` only orders container *startup*, not readiness. Under Podman the
# db healthcheck is not waited on, so Apache used to come up first and the
# installer failed with a connection error. Probing with PHP needs no extra
# package and uses the same client stack the installer itself uses.
QLO_DB_HOST="${QLO_DB_HOST:-db}"
QLO_DB_PORT="${QLO_DB_PORT:-3306}"
export QLO_DB_HOST QLO_DB_PORT

log "Waiting for MariaDB at ${QLO_DB_HOST}:${QLO_DB_PORT} ..."
tries=0
until php -r '
    $h = getenv("QLO_DB_HOST");
    $p = (int) getenv("QLO_DB_PORT");
    $s = @fsockopen($h, $p, $errno, $errstr, 2);
    if ($s) { fclose($s); exit(0); }
    exit(1);
' 2>/dev/null; do
    tries=$((tries + 1))
    if [ "$tries" -ge 90 ]; then
        warn "ERROR: MariaDB did not answer on ${QLO_DB_HOST}:${QLO_DB_PORT} after 90 tries (~3 minutes)."
        warn "Check the database container: podman-compose ps ; podman-compose logs db"
        exit 1
    fi
    sleep 2
done
log "MariaDB is accepting connections (after ${tries} probe(s))."

# ---------------------------------------------------------------------------
# 2. Decide the identity Apache runs as
# ---------------------------------------------------------------------------
ROOTLESS=0
if [ -f /run/.containerenv ] && grep -q 'rootless=1' /run/.containerenv 2>/dev/null; then
    ROOTLESS=1
fi

# Apache resolves User/Group by NAME, so whatever numeric ids we settle on must
# have entries in this container's /etc/passwd and /etc/group. Create them if
# absent. Both files are part of the image and are never bind-mounted, so this
# cannot affect the host. Results are returned in globals rather than on stdout,
# because a message printed to stdout would be captured by command substitution.
RESOLVED_NAME=''

ensure_group() {
    _gid="$1"
    if ! getent group "$_gid" >/dev/null 2>&1; then
        echo "qlohost:x:${_gid}:" >> /etc/group
        log "Added /etc/group entry 'qlohost' for gid ${_gid}."
    fi
    RESOLVED_NAME="$(getent group "$_gid" 2>/dev/null | cut -d: -f1)"
    [ -n "$RESOLVED_NAME" ] || RESOLVED_NAME="www-data"
}

ensure_user() {
    _uid="$1"; _gid="$2"
    if ! getent passwd "$_uid" >/dev/null 2>&1; then
        echo "qlohost:x:${_uid}:${_gid}::${APP_ROOT}:/bin/false" >> /etc/passwd
        log "Added /etc/passwd entry 'qlohost' for uid ${_uid}."
    fi
    RESOLVED_NAME="$(getent passwd "$_uid" 2>/dev/null | cut -d: -f1)"
    [ -n "$RESOLVED_NAME" ] || RESOLVED_NAME="www-data"
}

# Who owns the bind-mounted tree, as seen from inside this container?
#   rootful Docker            -> the host user (e.g. 1000:1000)
#   rootless Podman, default  -> 0:0 (container root IS the host user)
#   rootless Podman, keep-id  -> the host uid/gid, unmapped
MOUNT_UID="$(stat -c %u "$APP_ROOT/config" 2>/dev/null || echo '')"
MOUNT_GID="$(stat -c %g "$APP_ROOT/config" 2>/dev/null || echo '')"

NEEDS_GROUP_WRITE=1
RUN_USER=www-data
RUN_GROUP=www-data

if [ -n "$MOUNT_UID" ] && [ "$MOUNT_UID" != "0" ]; then
    # Preferred: run Apache as the identity that already owns the files.
    RUN_GID="${MOUNT_GID:-$MOUNT_UID}"
    ensure_group "$RUN_GID"; RUN_GROUP="$RESOLVED_NAME"
    ensure_user "$MOUNT_UID" "$RUN_GID"; RUN_USER="$RESOLVED_NAME"
    NEEDS_GROUP_WRITE=0
    log "Bind mount is owned by uid=${MOUNT_UID} gid=${MOUNT_GID}."
    log "Running Apache AS that identity (${RUN_USER}:${RUN_GROUP}); no host ownership or permission changes needed."
elif [ -n "$MOUNT_GID" ]; then
    # Fallback: the mount looks owned by uid 0 and Apache refuses uid 0.
    ensure_group "$MOUNT_GID"; RUN_GROUP="$RESOLVED_NAME"
    log "Bind mount appears owned by uid=${MOUNT_UID:-0} gid=${MOUNT_GID}."
    log "Apache cannot run as uid 0, so running as ${RUN_USER}:${RUN_GROUP} and adding group-write."
else
    warn "WARNING: could not stat ${APP_ROOT}/config; falling back to ${RUN_USER}:${RUN_GROUP} with group-write."
fi

if [ "$ROOTLESS" -eq 1 ] && [ "$NEEDS_GROUP_WRITE" -eq 1 ]; then
    log "Rootless Podman with the default user namespace."
    log "  Files Apache creates in cache/, img/, log/ and upload/ will be owned by"
    log "  container uid 33, which appears on your host as a uid in your subuid range:"
    log "      podman unshare cat /proc/self/uid_map"
    log "  Those paths are gitignored and you own their parent directories, so you can"
    log "  delete them from the host normally. To have them arrive owned by you instead,"
    log "  see doc 05 section 10.3.1 (--userns=keep-id), which also removes the need"
    log "  for group-write entirely."
fi

if [ "$NEEDS_GROUP_WRITE" -eq 1 ]; then
    # Everything Apache creates from here on (Smarty compile cache, uploaded
    # images, logs) inherits this umask. 002 yields 775/664, i.e. group-writable,
    # and the group is the one the host user belongs to - so generated files can
    # be managed from the host. Without it, subtrees Apache creates arrive as
    # drwxr-xr-x owned by a container uid that maps to a host subuid, and the
    # user hits "Permission denied" trying to clear caches from their own
    # machine. (Directories created BEFORE this umask existed still need
    # `podman unshare` or a container-side rm once; see doc 05 section 10.3.)
    umask 002
    # `X` grants execute to directories only, which is what traversal needs, and
    # leaves regular files non-executable.
    log "Granting group-write to the paths the installer checks ..."
    chmod g+w "$APP_ROOT" 2>/dev/null || true   # for the root .htaccess QloApps generates
    for p in $QLO_WRITABLE_PATHS; do
        if [ -e "$APP_ROOT/$p" ]; then
            chmod -R g+rwX "$APP_ROOT/$p" 2>/dev/null || warn "  note: could not chmod $p (leaving as-is)"
        else
            warn "  MISSING: $p"
        fi
    done
else
    # Nothing to change, but still report a bad checkout clearly.
    for p in $QLO_WRITABLE_PATHS; do
        [ -e "$APP_ROOT/$p" ] || warn "  MISSING: $p"
    done
fi

# Apache's own runtime dirs live in the image, not on the bind mount. They ship
# as root:www-data 750, which a different identity cannot write.
for d in /var/log/apache2 /var/run/apache2 /var/lock/apache2; do
    mkdir -p "$d" 2>/dev/null || true
    chown -R "${RUN_USER}:${RUN_GROUP}" "$d" 2>/dev/null || true
    chmod -R u+rwX,g+rwX "$d" 2>/dev/null || true
done

export APACHE_RUN_USER="$RUN_USER"
export APACHE_RUN_GROUP="$RUN_GROUP"

# ---------------------------------------------------------------------------
# 3. Report, then hand over
# ---------------------------------------------------------------------------
# /etc/apache2/envvars sets these with ":=" which only assigns when unset or
# empty, so the values exported above survive. Verify rather than assume: the
# Dockerfile already asserts the file's shape at build time, and this proves it
# again at runtime, because "Apache runs as the wrong user" otherwise presents
# as a confusing pile of permission-denied errors during install.
_resolved_user="$( ( . /etc/apache2/envvars >/dev/null 2>&1; echo "$APACHE_RUN_USER" ) 2>/dev/null || echo '' )"
_resolved_group="$( ( . /etc/apache2/envvars >/dev/null 2>&1; echo "$APACHE_RUN_GROUP" ) 2>/dev/null || echo '' )"
log "/etc/apache2/envvars resolves APACHE_RUN_USER  to: ${_resolved_user:-<empty>}"
log "/etc/apache2/envvars resolves APACHE_RUN_GROUP to: ${_resolved_group:-<empty>}"

case "$_resolved_user" in
    ''|root|'#0')
        warn "ERROR: Apache would run as '${_resolved_user}'. httpd refuses uid 0"
        warn "       (mod_unixd.c unixd_set_user) and would not start."
        exit 1
        ;;
esac

if ! getent passwd "$_resolved_user" >/dev/null 2>&1; then
    warn "ERROR: '${_resolved_user}' has no /etc/passwd entry; Apache would abort"
    warn "       with APLOGNO(02155) getpwuid."
    exit 1
fi

# Sanity-check that the installer's own target is actually writable by the
# identity Apache will use, so a wall of PHP permission errors becomes one
# actionable line.
#
# This is done arithmetically rather than by switching user, and the distinction
# matters. set_group_privs() in mod_unixd.c does setgid(group_id) using the
# Group directive's value, which is NOT necessarily the gid in that user's
# passwd entry. `su -s /bin/sh www-data` would use the passwd gid and report a
# false "cannot write" - precisely the misleading-warning failure mode this
# script is trying to eliminate. Comparing the file's uid/gid/mode against the
# uid/gid Apache will actually run with reproduces the kernel's own check.
check_writable() {
    _path="$1"
    _tuid="$(getent passwd "$_resolved_user" 2>/dev/null | cut -d: -f3)"
    _tgid="$(getent group  "$_resolved_group" 2>/dev/null | cut -d: -f3)"
    if [ -z "$_tuid" ] || [ -z "$_tgid" ]; then
        warn "note: could not resolve ${_resolved_user}:${_resolved_group} to numeric ids; writability self-check skipped."
        return 0
    fi
    _fuid="$(stat -c %u "$_path" 2>/dev/null || echo '')"
    _fgid="$(stat -c %g "$_path" 2>/dev/null || echo '')"
    _mode="$(stat -c %a "$_path" 2>/dev/null || echo '')"
    if [ -z "$_fuid" ] || [ -z "$_mode" ]; then
        warn "note: could not stat ${_path}; writability self-check skipped."
        return 0
    fi
    # last three octal digits = owner/group/other; strip any setuid/sticky digit
    _ugo="${_mode#"${_mode%???}"}"
    _o="$(printf %s "$_ugo" | cut -c1)"
    _g="$(printf %s "$_ugo" | cut -c2)"
    _x="$(printf %s "$_ugo" | cut -c3)"
    if [ "$_fuid" = "$_tuid" ]; then
        _bit=$((_o & 2))
    elif [ "$_fgid" = "$_tgid" ]; then
        _bit=$((_g & 2))
    else
        _bit=$((_x & 2))
    fi
    if [ "$_bit" -eq 0 ]; then
        warn "WARNING: Apache (uid ${_tuid}, gid ${_tgid}) cannot write ${_path},"
        warn "         which is owned by uid ${_fuid}, gid ${_fgid}, mode ${_mode}."
        warn "         The installer will fail with permission errors."
    else
        log "Self-check: Apache (uid ${_tuid}, gid ${_tgid}) can write ${_path}."
    fi
}

check_writable "$APP_ROOT/config"

log "Starting Apache as ${_resolved_user}:${_resolved_group} ..."

# docker-php-entrypoint re-execs apache2-foreground, which sources envvars and
# creates the APACHE_*_DIR directories before dropping privileges.
exec docker-php-entrypoint "$@"
