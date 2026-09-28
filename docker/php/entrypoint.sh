#!/bin/sh
set -eu

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader

if ! grep -qE '^APP_KEY=base64:.+' .env; then
    echo 'APP_KEY no está configurada. Define una clave antes de iniciar la aplicación.' >&2
    exit 1
fi

php artisan storage:link --force >/dev/null 2>&1 || true

if [ "${APP_RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --ansi
fi

# Nunca ejecutar db:seed al iniciar: los datos de demostración requieren una acción explícita.
exec "$@"