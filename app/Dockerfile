# =====================================================================
# Dockerfile aplikasi Laravel (multi-stage, non-root, image ramping)
# Build 1x di CI -> image immutable di-deploy ke server.
# =====================================================================

# ---- Stage 1: vendor (composer install dengan lock) ----
FROM composer:2 AS vendor
WORKDIR /app
COPY app/composer.json app/composer.lock ./
# --no-scripts: tidak jalankan post-install yang butuh DB saat build
RUN composer install --no-dev --no-interaction --no-progress --no-scripts \
    --prefer-dist --optimize-autoloader

# ---- Stage 2: runtime ----
FROM php:8.3-fpm-bookworm

# System deps + ekstensi PHP
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpq-dev libicu-dev libzip-dev libpng-dev libjpeg-dev libwebp-dev \
        libfreetype6-dev unzip git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql intl zip bcmath opcache gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# OpCache produksi (kecepatan + keamanan)
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'expose_php=0'; \
} > /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html

# Salin source aplikasi
COPY app/ /var/www/html/

# Vendor dari stage 1
COPY --from=vendor /app/vendor /var/www/html/vendor

# Storage logs harus bisa ditulis; lainnya read-only sewajarnya
RUN mkdir -p /var/www/html/storage/framework/{cache,sessions,views} \
    && mkdir -p /var/www/html/storage/logs /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

USER www-data

EXPOSE 9000
CMD ["php-fpm"]