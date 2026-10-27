# Image demo untuk deploy Railway: PHP 8.3 + ekstensi yang dipakai Laravel.
#
# Catatan: composer install dijalankan saat build image; migrate & seed saat
# container start lewat `docker-entrypoint.sh`. Seed hanya jalan saat database
# masih baru, dan APP_KEY disimpan di database/app_key agar stabil.

FROM php:8.3-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev libonig-dev unzip \
    && docker-php-ext-install pdo_sqlite pdo_mysql mbstring pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# $PORT diisi Railway saat runtime.
CMD ["sh", "docker-entrypoint.sh"]
