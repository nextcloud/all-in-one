FROM docker.io/library/composer:2@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac

RUN pecl bundle -d /usr/src/php/ext apcu \
 && docker-php-ext-install apcu