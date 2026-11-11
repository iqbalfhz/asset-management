# Image produksi untuk Coolify (build pack: Dockerfile). Satu container: FrankenPHP melayani web;
# antrean dan jadwal dijalankan oleh Scheduled Task Coolify (`php artisan schedule:run` tiap menit).

# Dasar: PHP 8.5 (rilis stabil terbaru; PHP tidak punya jalur LTS) di atas FrankenPHP dan Debian 13 (trixie).
# Ekstensi: intl untuk format Rupiah, gd untuk gambar di PDF. Klien MySQL untuk dump database oleh spatie/laravel-backup.
FROM serversideup/php:8.5-frankenphp-trixie-v5.1.1 AS base
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

# Aset front-end dengan Node 26 (LTS terbaru). Tema Kertas mengimpor CSS dari vendor/filament, jadi butuh folder vendor.
FROM node:26-trixie-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor
RUN npm run build

FROM vendor AS app
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

# HTTP biasa di port 8080; HTTPS ditangani Cloudflare. Migrasi dan cache Laravel dijalankan otomatis saat container start.
ENV PHP_OPCACHE_ENABLE=1 \
    SSL_MODE=off \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=true

EXPOSE 8080
