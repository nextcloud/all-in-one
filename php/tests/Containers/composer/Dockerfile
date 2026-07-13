FROM docker.io/library/composer:2@sha256:5946476338742b200bb9ff88f8be56275ddae4b3949c72305cb0dbf10cfcb760

RUN pecl bundle -d /usr/src/php/ext apcu \
 && docker-php-ext-install apcu