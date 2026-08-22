#!/bin/sh
set -e

echo "==> Waiting for MySQL (${DB_HOST}:${DB_PORT})..."
until mysqladmin ping -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" --silent 2>/dev/null; do
  sleep 2
done
echo "==> MySQL is ready."

if [ ! -f .env ]; then
  echo "==> Creating .env from .env.example"
  cp .env.example .env
fi

# Jangan overwrite DB_* di .env — pakai nilai dari .env / environment Compose
# (supaya tetap bisa pakai MySQL lokal Navicat via host.docker.internal).
if [ -n "${APP_URL:-}" ]; then
  php -r '
  $env = file_get_contents(".env");
  $url = getenv("APP_URL") ?: "http://localhost:8900";
  if (preg_match("/^APP_URL=.*/m", $env)) {
    $env = preg_replace("/^APP_URL=.*/m", "APP_URL={$url}", $env);
  } else {
    $env .= PHP_EOL . "APP_URL={$url}";
  }
  file_put_contents(".env", $env);
  '
fi

if [ ! -d vendor ]; then
  echo "==> composer install"
  composer install --no-interaction --prefer-dist
fi

if ! grep -qE '^APP_KEY=base64:' .env; then
  echo "==> php artisan key:generate"
  php artisan key:generate --force
fi

mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache || true

if [ ! -d node_modules ]; then
  echo "==> npm install"
  npm install
fi

if [ ! -d public/build ]; then
  echo "==> npm run build"
  npm run build
fi

# Jangan menimpa database Navicat yang sudah diimport.
# migrate/seed hanya jika dipaksa, atau jika tabel users belum ada.
user_count="$(mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" \
  -N -s -e "SELECT COUNT(*) FROM \`${DB_DATABASE}\`.users" 2>/dev/null || echo 0)"

if [ "${AUTO_MIGRATE:-false}" = "true" ]; then
  echo "==> php artisan migrate --force (AUTO_MIGRATE=true)"
  php artisan migrate --force || echo "==> migrate gagal, lanjut start (data tidak dihapus)."
elif [ "${user_count:-0}" = "0" ]; then
  echo "==> php artisan migrate --force (database masih kosong)"
  php artisan migrate --force || echo "==> migrate gagal, lanjut start."
else
  echo "==> Skip migrate: ${DB_DATABASE} sudah ada data (users=${user_count})."
fi

if [ "${SEED_ON_START:-false}" = "true" ]; then
  echo "==> php artisan db:seed --force"
  php artisan db:seed --force || true
else
  echo "==> Skip db:seed (SEED_ON_START=false)."
fi

echo "==> Starting: $*"
exec "$@"
