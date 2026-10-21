#!/bin/sh
set -eu

cd /var/www/html

if [ "${APP_ENV:-local}" != "production" ] && [ ! -f .env ]; then
    cp .env.example .env
fi

if [ -z "${APP_KEY:-}" ]; then
    echo 'APP_KEY no está configurada. Define una clave antes de iniciar la aplicación.' >&2
    exit 1
fi

php artisan storage:link --force >/dev/null 2>&1 || true

if [ "${APP_RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --ansi
fi

# Las cachés pueden usar la base de datos en producción. Limpiarlas después de
# migrar evita consultar tablas como `cache` antes de que hayan sido creadas.
php artisan optimize:clear --no-interaction
php artisan config:cache --no-interaction
php artisan event:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

# Nunca ejecutar db:seed al iniciar: los datos de demostración requieren una acción explícita.
exec "$@"
