FROM node:22-alpine AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM composer:2 AS vendor-dev

WORKDIR /app
COPY . .
RUN composer install \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-req=ext-sockets \
    --no-scripts

FROM vendor-dev AS vendor

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-req=ext-sockets \
    --no-scripts

FROM php:8.3-fpm-alpine AS app

RUN apk add --no-cache \
        icu-libs \
        libzip \
        oniguruma \
    && apk add --no-cache --virtual .build-deps \
        icu-dev \
        libzip-dev \
        linux-headers \
        oniguruma-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        mbstring \
        pcntl \
        pdo_mysql \
        sockets \
        zip \
    && apk del .build-deps

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY docker/php/php.ini /usr/local/etc/php/conf.d/file-vault.ini
COPY docker/php/entrypoint.sh /usr/local/bin/file-vault-entrypoint

RUN chmod +x artisan /usr/local/bin/file-vault-entrypoint \
    && mkdir -p \
        storage/app/private/documents/uploads \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

ENTRYPOINT ["file-vault-entrypoint"]
CMD ["php-fpm", "-F"]

FROM app AS test

USER root
COPY --from=vendor-dev /app/vendor ./vendor
RUN chown -R www-data:www-data vendor
USER www-data
CMD ["php", "artisan", "test"]

FROM nginx:1.27-alpine AS nginx

WORKDIR /var/www/html
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public ./public
COPY --from=frontend /app/public/build ./public/build
