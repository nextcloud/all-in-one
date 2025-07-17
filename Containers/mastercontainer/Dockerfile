# syntax=docker/dockerfile:latest
# Docker CLI is a requirement
FROM docker:28.3.2-cli AS docker

# Caddy is a requirement
FROM caddy:2.10.0-alpine AS caddy

# From https://github.com/docker-library/php/blob/master/8.4/alpine3.22/fpm/Dockerfile
FROM php:8.4.10-fpm-alpine3.22

ARG AIO_GIT_URL="https://github.com/nextcloud-releases/all-in-one.git"
ARG AIO_GIT_BRANCH="main"

EXPOSE 80
EXPOSE 8080
EXPOSE 8443

COPY --from=caddy /usr/bin/caddy /usr/bin/caddy
COPY --from=docker /usr/local/bin/docker /usr/local/bin/docker

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
        wget \
        bash \
        apache2 \
        apache2-proxy \
        apache2-ssl \
        supervisor \
        openssl \
        sudo \
        netcat-openbsd \
        curl \
        grep; \
    \
    apk add --no-cache --virtual .build-deps \
        autoconf \
        build-base; \
    pecl install APCu-5.1.24; \
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
    grep -q ';listen.allowed_clients' /usr/local/etc/php-fpm.d/www.conf; \
    sed -i 's|;listen.allowed_clients.*|listen.allowed_clients = 127.0.0.1,::1|' /usr/local/etc/php-fpm.d/www.conf; \
    \
    apk add --no-cache git; \
    wget https://getcomposer.org/installer -O - | php -- --install-dir=/usr/local/bin --filename=composer; \
    chmod +x /usr/local/bin/composer; \
    cd /var/www/docker-aio; \
    git clone "$AIO_GIT_URL" --depth 1 --single-branch --branch "$AIO_GIT_BRANCH" .; \
    find ./ -maxdepth 1 -mindepth 1 -not -path ./php -not -path ./community-containers -exec rm -r {} \; ; \
    rm -r ./php/tests; \
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
    mkdir -p /etc/apache2/certs; \
    cd /etc/apache2/certs; \
    openssl req -new -newkey rsa:4096 -days 3650 -nodes -x509 -subj "/C=DE/ST=BE/L=Local/O=Dev/CN=nextcloud.local" -keyout /etc/apache2/certs/ssl.key -out /etc/apache2/certs/ssl.crt; \
    \
    sed -i \
            -e '/^Listen /d' \
            -e 's/^LogLevel .*/LogLevel error/' \
            -e 's|^ErrorLog .*|ErrorLog /proc/self/fd/2|' \
            -e 's/User apache/User www-data/g' \
            -e 's/Group apache/Group www-data/g' \
            -e 's/^#\(LoadModule .*mod_rewrite.so\)/\1/' \
            -e 's/^#\(LoadModule .*mod_headers.so\)/\1/' \
            -e 's/^#\(LoadModule .*mod_env.so\)/\1/' \
            -e 's/^#\(LoadModule .*mod_mime.so\)/\1/' \
            -e 's/^#\(LoadModule .*mod_dir.so\)/\1/' \
            -e 's/^#\(LoadModule .*mod_authz_core.so\)/\1/' \
            -e 's/^#\(LoadModule .*mod_mpm_event.so\)/\1/' \
            -e 's/\(LoadModule .*mod_mpm_worker.so\)/#\1/' \
            -e 's/\(LoadModule .*mod_mpm_prefork.so\)/#\1/' \
            -e 's/\(ScriptAlias \)/#\1/' \
        /etc/apache2/httpd.conf; \
        mkdir -p /etc/apache2/logs; \
        rm /etc/apache2/conf.d/ssl.conf; \
        echo "ServerName localhost" | tee -a /etc/apache2/httpd.conf; \
        grep -q '^LoadModule lbmethod_heartbeat_module' /etc/apache2/conf.d/proxy.conf; \
        sed -i 's|^LoadModule lbmethod_heartbeat_module.*|#LoadModule lbmethod_heartbeat_module|' /etc/apache2/conf.d/proxy.conf; \
        echo "SSLSessionCache nonenotnull" | tee -a /etc/apache2/httpd.conf; \
        echo "LoadModule ssl_module modules/mod_ssl.so" | tee -a /etc/apache2/httpd.conf; \
        echo "LoadModule socache_shmcb_module modules/mod_socache_shmcb.so" | tee -a /etc/apache2/httpd.conf; \
        echo "Include /etc/apache2/sites-available/mastercontainer.conf" | tee -a /etc/apache2/httpd.conf; \
    \
    rm -f /etc/apache2/conf.d/default.conf \
          /etc/apache2/conf.d/userdir.conf \
          /etc/apache2/conf.d/info.conf; \
    \
    rm -rf /var/www/localhost/cgi-bin/; \
    mkdir /var/log/supervisord; \
    mkdir /var/run/supervisord;

COPY --chmod=775 *.sh /
COPY --chmod=664 Caddyfile /Caddyfile
COPY --chmod=664 supervisord.conf /supervisord.conf
COPY mastercontainer.conf /etc/apache2/sites-available/mastercontainer.conf

LABEL org.label-schema.vendor="Nextcloud"

# hadolint ignore=DL3002
USER root

ENTRYPOINT ["/start.sh"]

HEALTHCHECK CMD /healthcheck.sh
