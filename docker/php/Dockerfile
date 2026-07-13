FROM php:8.4-fpm-alpine

RUN apk add --no-cache git unzip icu-dev postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_pgsql intl opcache \
    && apk del --no-network icu-dev postgresql-dev \
    && apk add --no-cache icu-libs libpq

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ARG UID=1000
ARG GID=1000
RUN addgroup -g ${GID} -S app && adduser -u ${UID} -S app -G app

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

WORKDIR /app
USER app
