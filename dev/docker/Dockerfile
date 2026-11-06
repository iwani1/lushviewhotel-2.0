# Local development image for the Lush View Hotel platform.
#
# Base is pinned to PHP 8.4 because that is what the live GreenGeeks server
# runs (PHP 8.4.27). composer.json requires ">=8.2 <8.6", so 8.4 is both
# supported and production-faithful.
FROM php:8.4-apache-bookworm

# System libraries required to build the PHP extensions QloApps needs.
# mariadb-client is included so the import/reset scripts in dev/scripts can run
# from inside this container without a second image.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        libxml2-dev \
        libonig-dev \
        libexif-dev \
        mariadb-client \
        unzip \
    && rm -rf /var/lib/apt/lists/*

# The installer (install-dev/controllers/http/system.php:88-103) requires:
#   gd, pdo_mysql, curl, soap, openssl, simplexml, zip
# curl / openssl / simplexml / dom / phar are already compiled into the base
# image, so only these four are built here.
#
# exif and mbstring are NOT required - classes/ImageManager.php:158 guards them
# with function_exists(). They are installed anyway so EXIF orientation handling
# works locally instead of being silently skipped.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        gd \
        pdo_mysql \
        soap \
        zip \
        exif \
        mbstring \
    && docker-php-ext-enable opcache

# QloApps generates the root .htaccess itself during install, so Apache must be
# allowed to honour it. The stock image sets "AllowOverride None" for
# /var/www/html, which silently breaks friendly URLs.
RUN a2enmod rewrite \
    && sed -ri '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' \
        /etc/apache2/apache2.conf

# Debian's /etc/apache2/envvars hardcodes the Apache user:
#     export APACHE_RUN_USER=www-data
# apache2-foreground sources that file on every start, so an APACHE_RUN_USER we
# export from the entrypoint would simply be overwritten. Rewrite it to honour
# an existing environment value instead, falling back to www-data.
#
# This is what lets the entrypoint run Apache as container root under rootless
# Podman - where container root IS the host user - so bind-mounted source files
# keep the owner they already have instead of being chowned to a host subuid.
# Both the plain and already-parameterised forms are handled so the build does
# not depend on which one the base image ships.
RUN sed -i \
        -e 's|^export APACHE_RUN_USER=www-data$|export APACHE_RUN_USER=${APACHE_RUN_USER:-www-data}|' \
        -e 's|^export APACHE_RUN_GROUP=www-data$|export APACHE_RUN_GROUP=${APACHE_RUN_GROUP:-www-data}|' \
        /etc/apache2/envvars \
    && echo "--- /etc/apache2/envvars after patching ---" \
    && grep -n 'APACHE_RUN_USER\|APACHE_RUN_GROUP' /etc/apache2/envvars \
    && if grep -q '^export APACHE_RUN_USER=${APACHE_RUN_USER:-www-data}$' /etc/apache2/envvars; then \
           echo "--- OK: APACHE_RUN_USER can now be overridden from the environment ---"; \
       else \
           echo "--- WARNING: could not parameterise APACHE_RUN_USER. ---"; \
           echo "---          Apache will always run as www-data. That is fine ---"; \
           echo "---          under Docker, but under rootless Podman the entrypoint ---"; \
           echo "---          fallback will be used instead. ---"; \
       fi

COPY dev/docker/php.ini /usr/local/etc/php/conf.d/zz-qlo.ini

COPY dev/docker/entrypoint.sh /usr/local/bin/qlo-entrypoint.sh
RUN chmod +x /usr/local/bin/qlo-entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["qlo-entrypoint.sh"]
CMD ["apache2-foreground"]
