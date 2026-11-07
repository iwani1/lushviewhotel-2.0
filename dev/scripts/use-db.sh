#!/usr/bin/env bash
#
# Switch the local installation between the two development databases:
#
#   fresh  qloapps_fresh  - clean 1.8.0 install with sample data
#   live   qloapps_live   - your real production data (1,934 bookings, 1,257 guests)
#
# Usage:
#   dev/scripts/use-db.sh fresh
#   dev/scripts/use-db.sh live
#   dev/scripts/use-db.sh            # just show which one is active
#
# What it does: rewrites _DB_NAME_ in config/settings.inc.php and clears the
# caches that would otherwise serve data from the previous database. Clearing is
# not optional - QloApps caches the class index and compiled Smarty templates,
# and stale entries will make the switch appear to have done nothing.
set -euo pipefail

cd "$(dirname "$0")/../.."
SETTINGS="config/settings.inc.php"

current() {
    grep -oP "(?<=_DB_NAME_', ')[^']*" "$SETTINGS" 2>/dev/null || echo "(not installed yet)"
}

target="${1:-}"
if [ -z "$target" ]; then
    echo "Active database: $(current)"
    echo "Usage: dev/scripts/use-db.sh fresh|live"
    exit 0
fi

case "$target" in
    fresh) db="qloapps_fresh" ;;
    live)  db="qloapps_live"  ;;
    *) echo "error: unknown target '$target' (expected 'fresh' or 'live')" >&2; exit 1 ;;
esac

if [ ! -f "$SETTINGS" ]; then
    echo "error: $SETTINGS does not exist." >&2
    echo "       Run the installer first - see docs/project/05-local-development-setup.md" >&2
    exit 1
fi

# Preflight: verify the app's OWN credentials can open the target database
# BEFORE rewriting settings. Switching to a database that was never created, or
# never GRANTed to the app user, otherwise leaves the whole site throwing
# access errors with nothing pointing at the cause. Skipped when no container
# engine is reachable (containers stopped), so the switch still works offline.
ENG=""
for cand in podman docker; do
    if command -v "$cand" >/dev/null 2>&1 \
       && "$cand" exec -i qlo-db true >/dev/null 2>&1; then
        ENG="$cand"
        break
    fi
done
if [ -n "$ENG" ]; then
    u="$(grep -oP "(?<=_DB_USER_', ')[^']*" "$SETTINGS" 2>/dev/null || true)"
    p="$(grep -oP "(?<=_DB_PASSWD_', ')[^']*" "$SETTINGS" 2>/dev/null || true)"
    if [ -n "$u" ] && [ -n "$p" ] \
       && ! "$ENG" exec -i qlo-db mariadb -u"$u" -p"$p" -e "USE \`$db\`;" >/dev/null 2>&1; then
        echo "error: the app user '$u' cannot open database '$db' - not switching." >&2
        if [ "$db" = "qloapps_live" ]; then
            echo "       qloapps_live is created AND granted to '$u' by" >&2
            echo "       dev/scripts/import-live-dump.sh. Run that first (it needs your" >&2
            echo "       phpMyAdmin export of the live database), then switch." >&2
        else
            echo "       Check the containers are up and that the database exists." >&2
        fi
        exit 1
    fi
fi

before="$(current)"
sed -i "s/define('_DB_NAME_', '[^']*')/define('_DB_NAME_', '$db')/" "$SETTINGS"

# Confirm the rewrite actually happened rather than assuming it did.
after="$(current)"
if [ "$after" != "$db" ]; then
    echo "error: could not rewrite _DB_NAME_ (still '$after')." >&2
    echo "       Check the file is writable: ls -l $SETTINGS" >&2
    exit 1
fi

# Clear caches, preserving the index.php guards inside those directories.
#
# Prefer doing it INSIDE the app container. Under rootless Podman the files
# Apache generated belong to a container uid that maps to a host subuid, and the
# subtrees it created are drwxr-xr-x owned by that subuid - so a host-side rm
# dies with "Permission denied" half-way through and leaves a mixed cache.
# Inside the container the same rm runs as root against the bind mount and
# always succeeds. qlo-app is the fixed container_name in docker-compose.yml.
CLEAR='rm -f /var/www/html/cache/class_index.php; find /var/www/html/cache/smarty/compile /var/www/html/cache/smarty/cache -mindepth 1 -maxdepth 1 ! -name index.php -exec rm -rf {} +'
if [ -n "$ENG" ]; then
    "$ENG" exec -i qlo-app sh -c "$CLEAR" 2>/dev/null || ENG=""
fi
if [ -z "$ENG" ]; then
    rm -f cache/class_index.php 2>/dev/null || true
    find cache/smarty/compile -mindepth 1 -maxdepth 1 ! -name 'index.php' -exec rm -rf {} + 2>/dev/null || true
    find cache/smarty/cache    -mindepth 1 -maxdepth 1 ! -name 'index.php' -exec rm -rf {} + 2>/dev/null || true
fi

echo "Switched database: $before  ->  $after"
echo "Cleared: cache/class_index.php and compiled Smarty templates."
echo
echo "Next: reload http://localhost:8080 (the first request rebuilds the cache)."
if [ "$db" = "qloapps_live" ]; then
    echo
    echo "NOTE: you are now on real production data. Passwords imported from the live"
    echo "      database cannot be used to log in - see the 'Logging in to the live-data"
    echo "      database' section of docs/project/05-local-development-setup.md."
fi
