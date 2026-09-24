FROM php:8.4-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libsqlite3-dev \
    && docker-php-ext-install -j$(nproc) bcmath mbstring pdo_sqlite opcache \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY docker/preview/opcache.ini /usr/local/etc/php/conf.d/zz-preview-opcache.ini
COPY docker/preview/www.conf /usr/local/etc/php-fpm.d/zz-preview.conf
COPY . /var/www/html

RUN mkdir -p /var/www/html/storage/framework/cache /var/www/html/storage/framework/sessions /var/www/html/storage/framework/views /var/www/html/storage/logs /var/www/html/bootstrap/cache && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

USER www-data

CMD ["php-fpm", "-F"]