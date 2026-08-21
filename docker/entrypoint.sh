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

# Force Docker DB settings into .env (idempotent)
php -r '
$env = file_get_contents(".env");
$map = [
  "DB_CONNECTION" => getenv("DB_CONNECTION") ?: "mysql",
  "DB_HOST" => getenv("DB_HOST") ?: "mysql",
  "DB_PORT" => getenv("DB_PORT") ?: "3306",
  "DB_DATABASE" => getenv("DB_DATABASE") ?: "cogs_perhitungan",
  "DB_USERNAME" => getenv("DB_USERNAME") ?: "root",
  "DB_PASSWORD" => getenv("DB_PASSWORD") ?: "secret",
  "APP_URL" => getenv("APP_URL") ?: "http://localhost:8900",
];
foreach ($map as $key => $value) {
  $pattern = "/^{$key}=.*/m";
  $line = "{$key}={$value}";
  if (preg_match($pattern, $env)) {
    $env = preg_replace($pattern, $line, $env);
  } else {
    $env .= PHP_EOL . $line;
  }
}
file_put_contents(".env", $env);
'

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

echo "==> php artisan migrate --force"
php artisan migrate --force

if [ "${SEED_ON_START:-true}" = "true" ]; then
  echo "==> php artisan db:seed --force"
  php artisan db:seed --force || true
fi

echo "==> Starting: $*"
exec "$@"
