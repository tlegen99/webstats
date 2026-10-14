FROM php:8.3-fpm-alpine

ARG UID=1000
ARG GID=1000

RUN apk add --no-cache icu-libs libzip libpq \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev postgresql-dev linux-headers \
 && docker-php-ext-install intl zip pdo_pgsql pcntl bcmath opcache \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini

# Пользователь с тем же UID/GID, что у тебя на Mac: файлы из контейнера не будут принадлежать root
RUN if ! getent group "${GID}" >/dev/null; then addgroup -g "${GID}" app; fi \
 && adduser -D -u "${UID}" -G "$(getent group "${GID}" | cut -d: -f1)" app

USER app
WORKDIR /var/www/app
