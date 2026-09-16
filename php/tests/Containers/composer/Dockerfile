FROM docker.io/library/composer:2@sha256:aaeab4b6b031e0a88efb907f0f26b563532a644fc2f4ea0d000ecf8658f7a2b8

RUN pecl bundle -d /usr/src/php/ext apcu \
 && docker-php-ext-install apcu