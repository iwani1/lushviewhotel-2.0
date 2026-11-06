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

COPY dev/docker/php.ini /usr/local/etc/php/conf.d/zz-qlo.ini

COPY dev/docker/entrypoint.sh /usr/local/bin/qlo-entrypoint.sh
RUN chmod +x /usr/local/bin/qlo-entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["qlo-entrypoint.sh"]
CMD ["apache2-foreground"]
