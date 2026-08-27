#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/financiera/current}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

cd "$APP_DIR"

"$COMPOSER_BIN" install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci --no-audit --no-fund
npm run build

"$PHP_BIN" artisan down --retry=60
trap '"$PHP_BIN" artisan up' EXIT

"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan storage:link --force
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan event:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan queue:restart

"$PHP_BIN" artisan up
trap - EXIT
