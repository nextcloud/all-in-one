# syntax=docker/dockerfile:latest
# Docker CLI is a requirement
FROM docker:29.9.2-cli AS docker

# Caddy is a requirement
FROM caddy:2.11.7-alpine AS caddy

# Composer is needed to install the php dependencies
FROM composer:2.10.3 AS composer

# From https://github.com/docker-library/php/blob/master/8.5/alpine3.24/fpm/Dockerfile
FROM php:8.5.12-fpm-alpine3.24

ARG APCU_VERSION=5.1.28

EXPOSE 80
EXPOSE 8080
EXPOSE 8443

# Overwrite home variable for subservices
ENV HOME=/var/www

COPY --from=caddy /usr/bin/caddy /usr/bin/caddy
COPY --from=docker /usr/local/bin/docker /usr/local/bin/docker
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

COPY community-containers /var/www/docker-aio/community-containers
COPY php /var/www/docker-aio/php
COPY --chmod=775 Containers/mastercontainer/*.sh /
COPY --chmod=664 Containers/mastercontainer/*.Caddyfile /
COPY Containers/mastercontainer/dinit.d/ /etc/dinit.d/

WORKDIR /var/www/docker-aio

SHELL ["/bin/ash", "-o", "pipefail", "-c"]
# hadolint ignore=SC2086,DL3003
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
        dinit \
        su-exec \
        netcat-openbsd \
        curl \
        grep; \
    \
    apk add --no-cache --virtual .build-deps \
        autoconf \
        build-base; \
    mkdir -p /usr/src/php/ext/apcu; \
    curl -fsSL --retry 5 "https://github.com/krakjoe/apcu/archive/refs/tags/v$APCU_VERSION.tar.gz" \
        | tar -xz --strip-components=1 -C /usr/src/php/ext/apcu; \
    docker-php-ext-install apcu; \
    docker-php-source delete; \
    { \
        echo 'apc.shm_size=32M'; \
    } >> /usr/local/etc/php/conf.d/docker-php-ext-apcu.ini; \
    { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=32'; \
        echo 'opcache.interned_strings_buffer=8'; \
        echo 'opcache.max_accelerated_files=4000'; \
        echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini; \
    runDeps="$( \
        scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions \
            | tr ',' '\n' \
            | sort -u \
            | awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
    )"; \
    apk add --no-cache --virtual .nextcloud-aio-rundeps $runDeps; \
    apk del .build-deps; \
    rm -f /usr/src/php.tar.xz /usr/src/php.tar.xz.asc; \
    grep -q '^pm = dynamic' /usr/local/etc/php-fpm.d/www.conf; \
    sed -i 's/^pm = dynamic/pm = ondemand/' /usr/local/etc/php-fpm.d/www.conf; \
    sed -i 's/^pm.max_children =.*/pm.max_children = 80/' /usr/local/etc/php-fpm.d/www.conf; \
    sed -i 's|access.log = /proc/self/fd/2|access.log = /proc/self/fd/1|' /usr/local/etc/php-fpm.d/docker.conf; \
    grep -q '^listen =' /usr/local/etc/php-fpm.d/docker.conf; \
    sed -i 's|listen =.*|listen = /run/php.sock|' /usr/local/etc/php-fpm.d/docker.conf; \
    echo "listen.owner = www-data" | tee -a /usr/local/etc/php-fpm.d/docker.conf; \
    \
    apk add --no-cache git; \
    cd /var/www/docker-aio; \
    rm -r ./php/tests; \
    chown www-data:www-data -R /var/www/docker-aio; \
    cd php; \
    su-exec www-data composer install --no-dev; \
    su-exec www-data composer clear-cache; \
    cd ..; \
    rm -f /usr/local/bin/composer; \
    chown -R www-data:www-data /var/www; \
    # The app code must not be writable by www-data. Runtime data is stored in /mnt/docker-aio-config
    chown -R root:www-data /var/www/docker-aio; \
    chmod -R u=rwX,g=rX,o= /var/www/docker-aio; \
    rm -r php/data; \
    rm -r php/session; \
    \
    # Remove setuid/setgid bits (e.g. from shadow) as nothing needs them
    find / -xdev -type f \( -perm -4000 -o -perm -2000 \) -exec chmod u-s,g-s {} +;

# hadolint ignore=DL3048
LABEL org.opencontainers.image.title="Nextcloud All-in-One Mastercontainer" \
    org.opencontainers.image.description="Easy deployment and maintenance of a Nextcloud server with all dependencies and optional services" \
    org.opencontainers.image.url="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.source="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.vendor="Nextcloud" \
    org.opencontainers.image.documentation="https://github.com/nextcloud/all-in-one/blob/main/readme.md" \
    wud.watch="false" \
    dockhand.update="false" \
    com.docker.compose.project="nextcloud-aio"

# hadolint ignore=DL3002
USER root:root

# The php-fpm base image sets STOPSIGNAL SIGQUIT, which dinit ignores, so the container would never stop gracefully
STOPSIGNAL SIGTERM
ENTRYPOINT ["/start.sh"]

HEALTHCHECK CMD ["/healthcheck.sh"]
