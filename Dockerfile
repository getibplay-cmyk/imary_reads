# syntax=docker/dockerfile:1
FROM node:25-bookworm-slim AS assets
WORKDIR /src
COPY package*.json .npmrc ./
RUN npm ci --ignore-scripts
COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build

FROM php:8.3-fpm-bookworm AS app
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev libsqlite3-dev libzip-dev libicu-dev libonig-dev libpng-dev libjpeg62-turbo-dev \
    libwebp-dev libxml2-dev unzip ca-certificates clamav clamav-freshclam \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" pdo_pgsql pdo_mysql pdo_sqlite mbstring intl zip gd pcntl opcache \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-scripts --no-autoloader --no-interaction --no-progress
COPY . .
COPY --from=assets /src/public/build ./public/build
COPY deploy/freshclam.conf /etc/clamav/freshclam.conf
COPY deploy/php.ini /usr/local/etc/php/conf.d/imary.ini
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts \
 && php artisan package:discover --ansi \
 && mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chown -R clamav:clamav /var/lib/clamav
USER www-data
EXPOSE 9000
CMD ["php-fpm"]

FROM caddy:2-alpine AS web
COPY deploy/Caddyfile /etc/caddy/Caddyfile
COPY --from=app /var/www/html/public /var/www/html/public
