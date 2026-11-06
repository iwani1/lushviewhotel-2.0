# Local development image for the Lush View Hotel platform.
#
# Base is pinned to PHP 8.4 because that is what the live GreenGeeks server
# runs (PHP 8.4.27). composer.json requires ">=8.2 <8.6", so 8.4 is both
# supported and production-faithful.
#
# Fully qualified on purpose: Podman resolves bare names through
# /etc/containers/registries.conf.d/000-shortnames.conf, and relying on that
# mapping is how "mariadb" ended up pointing at registry.fedoraproject.org.
FROM docker.io/library/php:8.4-apache-bookworm

# System libraries required to build the PHP extensions QloApps needs.
# mariadb-client is included so the import/reset scripts in dev/scripts can run
# from inside this container without a second image.
#
# libonig-dev is deliberately absent - it is only needed to build mbstring, and
# mbstring is already compiled into the base image (building it again produces
# "warning: mbstring (mbstring) is already loaded!" and wastes several minutes).
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        libxml2-dev \
        libexif-dev \
        mariadb-client \
        unzip \
    && rm -rf /var/lib/apt/lists/*

# The installer (install-dev/controllers/http/system.php:88-103) requires:
#   gd, pdo_mysql, curl, soap, openssl, simplexml, zip
# Already present in the base image: curl, openssl, simplexml, dom, phar,
# mbstring. So only these five are built here.
#
# exif is NOT required - classes/ImageManager.php:158 guards it with
# function_exists(). It is built anyway so EXIF orientation handling works
# locally instead of being silently skipped.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        gd \
        pdo_mysql \
        soap \
        zip \
        exif \
    && docker-php-ext-enable opcache

# QloApps generates the root .htaccess itself during install, so Apache must be
# allowed to honour it. The stock image sets "AllowOverride None" for
# /var/www/html, which silently breaks friendly URLs.
RUN a2enmod rewrite \
    && sed -ri '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' \
        /etc/apache2/apache2.conf

# Apache's user comes from /etc/apache2/envvars, which apache2-foreground sources
# on every start. Debian bookworm already writes it in a form that honours an
# environment override:
#
#     : ${APACHE_RUN_USER:=www-data}
#     export APACHE_RUN_USER
#
# ":=" assigns the default ONLY when the variable is unset or empty, so an
# APACHE_RUN_USER exported by the entrypoint survives untouched and no patching
# is required.
#
# An earlier revision of this file assumed an unconditional
# "export APACHE_RUN_USER=www-data" and tried to rewrite it with sed. The pattern
# matched nothing, so the build printed a WARNING claiming the override had
# failed - when in fact it works. Both forms are now handled explicitly, and the
# message reports what was actually found.
#
# This matters because the entrypoint exports its own APACHE_RUN_GROUP so Apache
# runs with the group that already owns the bind-mounted source, instead of
# chowning that source to www-data. See dev/docker/entrypoint.sh for the full
# reasoning, including why Apache can never be run as uid 0.
RUN if grep -q 'APACHE_RUN_USER:=www-data' /etc/apache2/envvars; then \
        echo "--- OK: envvars already honours an APACHE_RUN_USER override (':=' form) ---"; \
    else \
        echo "--- envvars hardcodes the Apache user; parameterising it ---"; \
        sed -i \
            -e 's|^export APACHE_RUN_USER=www-data$|export APACHE_RUN_USER=\${APACHE_RUN_USER:-www-data}|' \
            -e 's|^export APACHE_RUN_GROUP=www-data$|export APACHE_RUN_GROUP=\${APACHE_RUN_GROUP:-www-data}|' \
            /etc/apache2/envvars; \
    fi; \
    grep -nE 'APACHE_RUN_(USER|GROUP)' /etc/apache2/envvars

COPY dev/docker/php.ini /usr/local/etc/php/conf.d/zz-qlo.ini

COPY dev/docker/entrypoint.sh /usr/local/bin/qlo-entrypoint.sh
RUN chmod +x /usr/local/bin/qlo-entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["qlo-entrypoint.sh"]
CMD ["apache2-foreground"]
