FROM docker.io/library/composer:2@sha256:7725eb4545c438629ae8bde3ef0bb9a5038ef566126ad878442a69007242d267

RUN pecl bundle -d /usr/src/php/ext apcu \
 && docker-php-ext-install apcu