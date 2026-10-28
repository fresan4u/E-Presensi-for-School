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

if [ -f .env.render ]; then
  cp .env.render .env
elif [ ! -f .env ]; then
  # Railway's Docker builder may omit dotfiles such as .env.render even when
  # they are tracked. Build a minimal production config from runtime values so
  # the container can still boot and Laravel can serve the API.
  cat > .env <<EOF
APP_NAME=E-Presensi
APP_ENV=${APP_ENV:-production}
APP_KEY=${APP_KEY:-}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-https://${RAILWAY_PUBLIC_DOMAIN:-localhost}}
LOG_CHANNEL=${LOG_CHANNEL:-stderr}
LOG_LEVEL=${LOG_LEVEL:-error}
DB_CONNECTION=${DB_CONNECTION:-sqlite}
SESSION_DRIVER=${SESSION_DRIVER:-database}
SESSION_LIFETIME=${SESSION_LIFETIME:-120}
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=${QUEUE_CONNECTION:-sync}
CACHE_STORE=${CACHE_STORE:-database}
MAIL_MAILER=log
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="E-Presensi"
ADMIN_NAME=${ADMIN_NAME:-"Andi Pratama"}
ADMIN_EMAIL=${ADMIN_EMAIL:-andi.pratama@sekolah.id}
ADMIN_PASSWORD=${ADMIN_PASSWORD:-password}
PETUGAS_NAME=${PETUGAS_NAME:-"Bagus Wijaya"}
PETUGAS_EMAIL=${PETUGAS_EMAIL:-bagus.wijaya@sekolah.id}
PETUGAS_PASSWORD=${PETUGAS_PASSWORD:-password}
STUDENT_NIS=${STUDENT_NIS:-2024000}
STUDENT_NAME=${STUDENT_NAME:-"Ahmad Fauzi"}
STUDENT_PIN=${STUDENT_PIN:-2024000}
WA_ENABLED=${WA_ENABLED:-false}
APP_TIMEZONE=${APP_TIMEZONE:-Asia/Jakarta}
EOF
fi

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
