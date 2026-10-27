#!/bin/sh
# Entrypoint backend (Railway).
#
# Tujuan:
# - `.env` selalu dibuat dari `.env.render` (konfigurasi deploy).
# - APP_KEY disimpan di `database/app_key` supaya stabil antar-restart. Kalau
#   key berubah, PIN siswa yang terenkripsi tidak bisa didekripsi lagi.
# - Seed hanya dijalankan saat database benar-benar baru, agar restart container
#   tidak mereset akun/PIN/absensi.
#
# Agar data benar-benar bertahan antar-deploy, mount Railway Volume ke
# `/app/database` (file SQLite + app_key ada di folder itu).
set -e

cd "$(dirname "$0")"

cp .env.render .env

mkdir -p database
touch database/database.sqlite

KEY_FILE=database/app_key
if [ -s "$KEY_FILE" ]; then
  APP_KEY_VALUE="$(cat "$KEY_FILE")"
  sed -i "s|^APP_KEY=.*|APP_KEY=$APP_KEY_VALUE|" .env
else
  php artisan key:generate --force
  grep '^APP_KEY=' .env | cut -d'=' -f2- > "$KEY_FILE"
fi

php artisan storage:link || true

# Cek sebelum migrate: file SQLite kosong berarti database baru.
FRESH=0
if [ ! -s database/database.sqlite ]; then
  FRESH=1
fi

php artisan migrate --force

if [ "$FRESH" = "1" ]; then
  php artisan db:seed --force
fi

php artisan serve --host 0.0.0.0 --port "${PORT:-8000}" &

while true; do
  php artisan schedule:run --no-interaction || true
  sleep 60
done
