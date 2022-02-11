# Docker CLI is a requirement
FROM docker:20.10.12-dind-alpine3.15 as dind

# From https://github.com/docker-library/php/blob/master/8.0/bullseye/apache/Dockerfile
FROM php:8.0.15-apache-bullseye

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
    ; \
    rm -rf /var/lib/apt/lists/*

RUN set -ex; \
    curl "https://caddyserver.com/api/download?os=linux&arch=$(dpkg-architecture --query DEB_BUILD_ARCH)" -o "/usr/bin/caddy" \
    && chmod +x /usr/bin/caddy \
    && /usr/bin/caddy version

COPY --from=dind /usr/local/bin/docker /usr/local/bin/
RUN chmod +x /usr/local/bin/docker

RUN mkdir -p /usr/src/php/ext/apcu && \
    curl -fsSL https://pecl.php.net/get/apcu | tar xvz -C "/usr/src/php/ext/apcu" --strip 1 && \
    docker-php-ext-install apcu

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
COPY cron.sh /
COPY supervisord.conf /
RUN chmod +x /usr/bin/start.sh; \
    chmod +x /cron.sh

USER root

ENTRYPOINT ["start.sh"]
CMD ["/usr/bin/supervisord", "-c", "/supervisord.conf"]
