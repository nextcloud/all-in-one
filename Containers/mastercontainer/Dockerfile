# Docker CLI is a requirement
FROM docker:23.0.3-cli as docker

# Caddy is a requirement
FROM caddy:2.6.4-alpine as caddy

# From https://github.com/docker-library/php/blob/master/8.1/alpine3.17/fpm/Dockerfile
FROM php:8.1.17-fpm-alpine3.17

RUN set -ex; \
    apk add --no-cache shadow; \
    groupmod -g 333 xfs; \
    usermod -u 333 -g 333 xfs; \
    groupmod -g 33 www-data; \
    usermod -u 33 -g 33 www-data

EXPOSE 80
EXPOSE 8080
EXPOSE 8443

RUN mkdir -p /var/www/docker-aio;

WORKDIR /var/www/docker-aio

RUN set -ex; \
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
        grep

RUN set -ex; \
    apk add --no-cache --virtual .build-deps \
        autoconf \
        build-base; \
    pecl install APCu-5.1.22; \
    docker-php-ext-enable apcu; \
    rm -r /tmp/pear; \
    \
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
    sed -i 's|access.log = /proc/self/fd/2|access.log = /proc/self/fd/1|' /usr/local/etc/php-fpm.d/docker.conf

COPY --from=caddy /usr/bin/caddy /usr/bin/
RUN chmod +x /usr/bin/caddy

COPY --from=docker /usr/local/bin/docker /usr/local/bin/
RUN chmod +x /usr/local/bin/docker

RUN set -e && \
    apk add --no-cache git; \
    wget https://getcomposer.org/installer -O - | php -- --install-dir=/usr/local/bin --filename=composer; \
    chmod +x /usr/local/bin/composer; \
    cd /var/www/docker-aio; \
    git clone https://github.com/nextcloud-releases/all-in-one.git --depth 1 .; \
    cd php; \
    composer install --no-dev; \
    composer clearcache; \
    cd ..; \
    rm -f /usr/local/bin/composer; \
    chmod 770 -R ./; \
    chown www-data:www-data -R /var/www; \
    rm -r ./php/data; \
    rm -r ./php/session; \
    apk del --no-cache git

RUN mkdir -p /etc/apache2/certs && \
    cd /etc/apache2/certs && \
    openssl req -new -newkey rsa:4096 -days 3650 -nodes -x509 -subj "/C=DE/ST=BE/L=Local/O=Dev/CN=nextcloud.local" -keyout ./ssl.key -out ./ssl.crt;

COPY mastercontainer.conf /etc/apache2/sites-available/

RUN sed -i \
            -e '/^Listen /d' \
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
        /etc/apache2/httpd.conf; \
        mkdir -p /etc/apache2/logs; \
        rm /etc/apache2/conf.d/ssl.conf; \
        echo "ServerName localhost" | tee -a /etc/apache2/httpd.conf; \
        echo "LoadModule ssl_module modules/mod_ssl.so" | tee -a /etc/apache2/httpd.conf; \
        echo "LoadModule socache_shmcb_module modules/mod_socache_shmcb.so" | tee -a /etc/apache2/httpd.conf; \
        echo "Include /etc/apache2/sites-available/mastercontainer.conf" | tee -a /etc/apache2/httpd.conf

RUN set -ex; \
    rm -f /etc/apache2/conf.d/default.conf \
          /etc/apache2/conf.d/userdir.conf \
          /etc/apache2/conf.d/info.conf

RUN mkdir /var/log/supervisord; \
    mkdir /var/run/supervisord;

COPY Caddyfile /
COPY start.sh /usr/bin/
COPY backup-time-file-watcher.sh /
COPY session-deduplicator.sh /
COPY cron.sh /
COPY daily-backup.sh /
COPY supervisord.conf /
COPY healthcheck.sh /
RUN chmod +x /usr/bin/start.sh; \
    chmod +x /cron.sh; \
    chmod +x /session-deduplicator.sh; \
    chmod +x /backup-time-file-watcher.sh; \
    chmod +x /daily-backup.sh; \
    chmod a+r /Caddyfile; \
    chmod +x /healthcheck.sh

USER root

ENTRYPOINT ["start.sh"]
CMD ["/usr/bin/supervisord", "-c", "/supervisord.conf"]

HEALTHCHECK CMD /healthcheck.sh
