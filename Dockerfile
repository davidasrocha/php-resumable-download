FROM composer:1.10 as composer
FROM php:7.2-cli

RUN apt-get update -y \
    && apt-get upgrade -y \
    && apt-get install -y \
        git \
        unzip \
        libssl-dev

RUN pecl install xdebug-2.9.6 \
    && docker-php-ext-enable xdebug

WORKDIR /var/www/html
COPY --from=composer /usr/bin/composer /usr/bin/composer

ENV PATH="$PATH:/var/www/html/vendor/bin"