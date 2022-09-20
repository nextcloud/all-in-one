# Docker CLI is a requirement
FROM docker:20.10.18-dind-alpine3.16 as dind

# Caddy is a requirement
FROM caddy:2.5.2-alpine as caddy

# From https://github.com/docker-library/php/blob/master/8.0/bullseye/apache/Dockerfile
FROM php:8.0.23-apache-bullseye

EXPOSE 80
EXPOSE 8080
EXPOSE 8443

RUN mkdir -p /mnt/docker-aio-config/;

VOLUME /mnt/docker-aio-config/

RUN mkdir -p /var/www/docker-aio;

WORKDIR /var/www/docker-aio

RUN apt-get update; \
    apt-get install -y --no-install-recommends \
        git \
        supervisor \
        openssl \
        sudo \
        dpkg-dev \
        netcat \
    ; \
    rm -rf /var/lib/apt/lists/*

COPY --from=caddy /usr/bin/caddy /usr/bin/
RUN chmod +x /usr/bin/caddy

COPY --from=dind /usr/local/bin/docker /usr/local/bin/
RUN chmod +x /usr/local/bin/docker

RUN set -ex; \
    pecl install APCu-5.1.22; \
    docker-php-ext-enable apcu

RUN set -e && \
    curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer && \
    chmod +x /usr/local/bin/composer && \
    cd /var/www/docker-aio; \
    git clone https://github.com/nextcloud-releases/all-in-one.git --depth 1 .; \
    cd php; \
    composer install --no-dev; \
    composer clearcache; \
    cd ..; \
    rm -f /usr/local/bin/composer; \
    chmod 770 -R ./; \
    chown www-data:www-data -R ./; \
    rm -r ./php/data; \
    rm -r ./php/session

RUN mkdir -p /etc/apache2/certs && \
    cd /etc/apache2/certs && \
    openssl req -new -newkey rsa:4096 -days 3650 -nodes -x509 -subj "/C=DE/ST=BE/L=Local/O=Dev/CN=nextcloud.local" -keyout ./ssl.key -out ./ssl.crt;

COPY mastercontainer.conf /etc/apache2/sites-available/

RUN a2enmod rewrite \
    headers \
    env \
    mime \
    dir \
    authz_core \
    proxy \
    proxy_http \
    ssl

RUN rm /etc/apache2/ports.conf; \
    sed -s -i -e "s/Include ports.conf//" /etc/apache2/apache2.conf; \
    sed -i "/^Listen /d" /etc/apache2/apache2.conf

RUN a2dissite 000-default && \
    a2dissite default-ssl && \
    a2ensite mastercontainer.conf

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