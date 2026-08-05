FROM docker.io/library/composer:2@sha256:4d71c3c2109c61d5415544264b59ad4087e4c5b7244481723664138fd36d5040

RUN pecl bundle -d /usr/src/php/ext apcu \
 && docker-php-ext-install apcu