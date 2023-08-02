# Docker CLI is a requirement
FROM docker:24.0.5-cli as docker

# Caddy is a requirement
FROM caddy:2.6.4-alpine as caddy

# From https://github.com/docker-library/php/blob/master/8.2/alpine3.18/fpm/Dockerfile
FROM php:8.2.8-fpm-alpine3.18

EXPOSE 80
EXPOSE 8080
EXPOSE 8443

COPY --from=caddy /usr/bin/caddy /usr/bin/caddy
COPY --from=docker /usr/local/bin/docker /usr/local/bin/docker

RUN set -ex; \
    apk add --no-cache shadow; \
    groupmod -g 333 xfs; \
    usermod -u 333 -g 333 xfs; \
    groupmod -g 33 www-data; \
    useradd -u 33 -g 33 www-data; \
    apk del --no-cache shadow; \
    \
    apk add --no-cache \
        util-linux-misc \
        ca-certificates \
        nss \
        wget \
        bash \
        apache2 \
        apache2-proxy \
        supervisor \
        sudo \
        netcat-openbsd \
        bind-tools \
        curl \
        grep; \
    \
    apk add --no-cache --virtual .build-deps \
        autoconf \
        build-base; \
    pecl install APCu-5.1.22; \
    docker-php-ext-enable apcu; \
    rm -r /tmp/pear; \
    runDeps="$( \
        scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions \
            | tr ',' '\n' \
            | sort -u \
            | awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
    )"; \
    apk add --virtual .nextcloud-aio-rundeps $runDeps; \
    apk del .build-deps; \
    grep -q '^pm = dynamic' /usr/local/etc/php-fpm.d/www.conf; \
    sed -i 's/^pm = dynamic/pm = ondemand/' /usr/local/etc/php-fpm.d/www.conf; \
    sed -i 's/^pm.max_children =.*/pm.max_children = 80/' /usr/local/etc/php-fpm.d/www.conf; \
    sed -i 's|access.log = /proc/self/fd/2|access.log = /proc/self/fd/1|' /usr/local/etc/php-fpm.d/docker.conf; \
    \
    wget https://getcomposer.org/installer -O - | php -- --install-dir=/usr/local/bin --filename=composer; \
    chmod +x /usr/local/bin/composer; \
    rm -rf /var/www/*; \
    mkdir -p /var/www/docker-aio; \
    cd /var/www/docker-aio; \
    apk add --no-cache git; \
    git clone https://github.com/nextcloud-releases/all-in-one.git --depth 1 .; \
    apk del --no-cache git; \
    find ./ -maxdepth 1 -mindepth 1 -not -path ./php -exec rm -r {} \; ; \
    chown www-data:www-data -R /var/www/docker-aio; \
    cd php; \
    sudo -u www-data composer install --no-dev; \
    sudo -u www-data composer clear-cache; \
    cd ..; \
    rm -f /usr/local/bin/composer; \
    chmod -R 770 /var/www/docker-aio; \
    chown -R www-data:www-data /var/www; \
    rm -r php/data; \
    rm -r php/session; \
    \
    mkdir /etc/apache2/logs; \
    mkdir /var/log/supervisord; \
    mkdir /var/run/supervisord;

COPY --chmod=775 *.sh /
COPY --chmod=664 Caddyfile /Caddyfile
COPY --chmod=664 supervisord.conf /supervisord.conf
COPY --chmod=664 apache.conf /etc/apache2/httpd.conf

WORKDIR /var/www/docker-aio

USER root

ENTRYPOINT ["/start.sh"]
CMD ["/usr/bin/supervisord", "-c", "/supervisord.conf"]

HEALTHCHECK CMD /healthcheck.sh
