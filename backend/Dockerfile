# Image demo untuk deploy Render: PHP 8.3 + ekstensi yang dipakai Laravel.
#
# Catatan: composer install dijalankan saat build image; migrate & seed saat
# container start (disk Render bersifat sementara, jadi database SQLite dibuat
# ulang setiap boot/deploy).

FROM php:8.3-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev libonig-dev unzip \
    && docker-php-ext-install pdo_sqlite pdo_mysql mbstring pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# $PORT diisi Render saat runtime.
CMD ["sh", "-c", "cp .env.render .env && touch database/database.sqlite && (grep -q '^APP_KEY=base64' .env || php artisan key:generate --force) && php artisan storage:link && php artisan migrate --force && php artisan db:seed --force && (php artisan serve --host 0.0.0.0 --port ${PORT:-8000} & while true; do php artisan schedule:run --no-interaction; sleep 60; done)"]
