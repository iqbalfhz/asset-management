# Image produksi untuk Coolify: PHP 8.5 (nginx + php-fpm) dan aset front-end dari Node 26 LTS.

# Dasar: ekstensi yang dipakai aplikasi (intl untuk format Rupiah, gd untuk gambar di PDF)
# dan klien MySQL untuk dump database oleh spatie/laravel-backup.
FROM serversideup/php:8.5-fpm-nginx-trixie-v5.1.1 AS base
USER root
RUN install-php-extensions intl gd \
    && apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client \
    && rm -rf /var/lib/apt/lists/*
USER www-data
WORKDIR /var/www/html

# Dependensi PHP tanpa paket dev; script composer ikut menerbitkan aset Filament ke public/.
FROM base AS vendor
COPY --chown=www-data:www-data . .
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Tema Kertas mengimpor CSS dari vendor/filament, jadi build Vite butuh folder vendor.
FROM node:26-trixie-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor
RUN npm run build

FROM vendor AS app
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

ENV PHP_OPCACHE_ENABLE=1 \
    SSL_MODE=off
