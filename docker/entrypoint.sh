#!/bin/sh
set -e

echo "Waiting for database at ${DB_HOST:-db}:${DB_PORT:-3306}..."
until php -r "new PDO('mysql:host=${DB_HOST:-db};port=${DB_PORT:-3306}', '${DB_USERNAME:-root}', '${DB_PASSWORD:-}');" 2>/dev/null; do
    sleep 2
done
echo "Database is up."

php artisan package:discover --ansi
php artisan migrate --force
php artisan storage:link || true
php artisan config:cache
php artisan route:cache

exec "$@"
