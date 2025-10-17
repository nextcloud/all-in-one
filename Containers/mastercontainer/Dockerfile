# syntax=docker/dockerfile:latest
# Docker CLI is a requirement
FROM docker:29.2.1-cli AS docker

# Caddy is a requirement
FROM caddy:2.11.1-alpine AS caddy

# From https://github.com/docker-library/php/blob/master/8.5/alpine3.23/fpm/Dockerfile
FROM php:8.5.3-fpm-alpine3.23

EXPOSE 80
EXPOSE 8080
EXPOSE 8443

# Overwrite home variable for subservices
ENV HOME=/var/www

COPY --from=caddy /usr/bin/caddy /usr/bin/caddy
COPY --from=docker /usr/local/bin/docker /usr/local/bin/docker

COPY community-containers /var/www/docker-aio/community-containers
COPY php /var/www/docker-aio/php
COPY --chmod=775 Containers/mastercontainer/*.sh /
COPY --chmod=664 Containers/mastercontainer/*.Caddyfile /
COPY --chmod=664 Containers/mastercontainer/supervisord.conf /supervisord.conf

WORKDIR /var/www/docker-aio

# hadolint ignore=SC2086,DL3047,DL3003,DL3004
RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache shadow; \
    groupmod -g 33 www-data; \
    usermod -u 33 -g 33 www-data; \
    \
    apk add --no-cache \
        util-linux-misc \
        ca-certificates \
        bash \
        supervisor \
        sudo \
        netcat-openbsd \
        curl \
        grep; \
    \
    apk add --no-cache --virtual .build-deps \
        autoconf \
        build-base; \
    pecl install APCu-5.1.28; \
    docker-php-ext-enable apcu; \
    rm -r /tmp/pear; \
    runDeps="$( \
        scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions \
            | tr ',' '\n' \
            | sort -u \
            | awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
    )"; \
    apk add --no-cache --virtual .nextcloud-aio-rundeps $runDeps; \
    apk del .build-deps; \
    grep -q '^pm = dynamic' /usr/local/etc/php-fpm.d/www.conf; \
    sed -i 's/^pm = dynamic/pm = ondemand/' /usr/local/etc/php-fpm.d/www.conf; \
    sed -i 's/^pm.max_children =.*/pm.max_children = 80/' /usr/local/etc/php-fpm.d/www.conf; \
    sed -i 's|access.log = /proc/self/fd/2|access.log = /proc/self/fd/1|' /usr/local/etc/php-fpm.d/docker.conf; \
    grep -q 'listen =' /usr/local/etc/php-fpm.d/www.conf; \
    sed -i 's|listen =.*|;listen = /run/php.sock # handled in zz-docker.conf|' /usr/local/etc/php-fpm.d/www.conf; \
    grep -q 'listen =' /usr/local/etc/php-fpm.d/zz-docker.conf; \
    sed -i 's|listen =.*|listen = /run/php.sock|' /usr/local/etc/php-fpm.d/zz-docker.conf; \
    \
    apk add --no-cache git; \
    curl https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer; \
    chmod +x /usr/local/bin/composer; \
    cd /var/www/docker-aio; \
    rm -r ./php/tests; \
    chown www-data:www-data -R /var/www/docker-aio; \
    cd php; \
    sudo -E -u www-data composer install --no-dev; \
    sudo -E -u www-data composer clear-cache; \
    cd ..; \
    rm -f /usr/local/bin/composer; \
    chmod -R 770 /var/www/docker-aio; \
    chown -R www-data:www-data /var/www; \
    rm -r php/data; \
    rm -r php/session; \
    \
    mkdir /var/log/supervisord; \
    mkdir /var/run/supervisord;

# hadolint ignore=DL3048
LABEL org.label-schema.vendor="Nextcloud" \
    wud.watch="false" \
    com.docker.compose.project="nextcloud-aio"

# hadolint ignore=DL3002
USER root

ENTRYPOINT ["/start.sh"]

HEALTHCHECK CMD /healthcheck.sh
