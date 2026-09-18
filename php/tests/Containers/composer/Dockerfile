FROM docker.io/library/composer:2@sha256:a5f59b9fd2faf31218632be4809dc6491761085e8064c31dc3b84378c48c248b

RUN pecl bundle -d /usr/src/php/ext apcu \
 && docker-php-ext-install apcu