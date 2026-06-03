FROM docker.io/library/composer:latest

RUN pecl bundle -d /usr/src/php/ext apcu \
 && docker-php-ext-install apcu