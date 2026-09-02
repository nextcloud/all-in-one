FROM docker.io/library/composer:2@sha256:8fa35f42911ff8bbee92aa37d781de6799168d4a0535ac6991f1b250bc2e0245

RUN pecl bundle -d /usr/src/php/ext apcu \
 && docker-php-ext-install apcu