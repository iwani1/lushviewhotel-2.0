#!/bin/sh
# Container entrypoint: make the directories QloApps must write to owned by the
# Apache user, then hand over to the stock PHP entrypoint.
#
# Why this is needed: the repository is bind-mounted from the host, so every
# file is owned by your host user. Apache inside the container runs as
# www-data (uid 33) and cannot write to them. The installer explicitly checks
# recursive write permission on the twelve paths listed below
# (install-dev/controllers/http/system.php) and refuses to continue without it.
#
# Note that .git is deliberately NOT in this list - changing its ownership would
# make git report "dubious ownership" on the host.
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

for d in $WRITABLE_DIRS; do
    if [ -d "/var/www/html/$d" ]; then
        chown -R www-data:www-data "/var/www/html/$d" 2>/dev/null || true
    fi
done

# The installer also writes the root .htaccess and config/settings.inc.php.
chown www-data:www-data /var/www/html 2>/dev/null || true

echo "[qlo-entrypoint] PHP $(php -r 'echo PHP_VERSION;') / Apache ready."
echo "[qlo-entrypoint] Database host is 'db' (not localhost) inside this network."

exec docker-php-entrypoint "$@"
