FROM docker.io/library/composer:2@sha256:9715c7f69044da2a212a5fbde29ee7da24e364d426560ae6367b060236f847d7

RUN pecl bundle -d /usr/src/php/ext apcu \
 && docker-php-ext-install apcu