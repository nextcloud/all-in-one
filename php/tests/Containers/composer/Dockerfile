FROM docker.io/library/composer:2@sha256:d8f6343d3fae98107426bc49163ccad46ef85aabd4a27d80a74401fab4aba332

RUN pecl bundle -d /usr/src/php/ext apcu \
 && docker-php-ext-install apcu