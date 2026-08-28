#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

composer install --no-interaction --prefer-dist

if ! grep -qE '^APP_KEY=base64:.+' .env; then
    php artisan key:generate --ansi --force
fi

php artisan storage:link --force >/dev/null 2>&1 || true
php artisan migrate --force --ansi
php artisan db:seed --force --ansi

exec "$@"
