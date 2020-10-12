FROM php:7.4-fpm

MAINTAINER Eugene Shaternik <eugene.sh@upservice.io>

RUN apt-get update && apt-get install -y \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libicu-dev \
        libzip-dev \
        libpq-dev \
        libxpm-dev \
        libvpx-dev \
        zip \
        vim \
        unzip \
        git \
        curl \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install -j$(nproc) intl \
    && docker-php-ext-install -j$(nproc) zip \
    && docker-php-ext-install -j$(nproc) pgsql \
    && docker-php-ext-install -j$(nproc) pdo_pgsql \
    && docker-php-ext-install -j$(nproc) exif \
    && docker-php-ext-configure gd

# Permission fix
RUN usermod -u 1000 www-data

EXPOSE 9000

