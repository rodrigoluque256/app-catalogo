FROM php:8.4-cli-alpine

RUN apk add --no-cache git unzip libpq-dev libzip-dev icu-dev linux-headers $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql zip intl bcmath pcntl \
    && apk del $PHPIZE_DEPS linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_HOME=/tmp/composer

WORKDIR /app
