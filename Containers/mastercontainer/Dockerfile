# Docker CLI is a requirement
FROM docker:20.10.21-dind-alpine3.16 as dind

# Caddy is a requirement
FROM caddy:2.6.2-alpine as caddy

# From https://github.com/docker-library/php/blob/master/8.1/alpine3.17/fpm/Dockerfile
FROM php:8.1.14-fpm-alpine3.17

EXPOSE 80
EXPOSE 8080
EXPOSE 8443

RUN mkdir -p /mnt/docker-aio-config/;

VOLUME /mnt/docker-aio-config/

RUN mkdir -p /var/www/docker-aio;

WORKDIR /var/www/docker-aio

RUN set -ex; \
    apk add --no-cache ca-certificates \
                       wget \
		       tzdata \
		       bash \
		       apache2 \
		       supervisor \
		       openssl \
		       netcat-openbsd \
		       autoconf \
		       build-base; \
    pecl install APCu-5.1.22; \
    docker-php-ext-enable apcu; \
    apk del --no-cache autoconf build-base

RUN set -ex; \
    apk add --no-cache shadow; \
    deluser www-data; \
    groupmod -g 333 xfs; \
    usermod -u 333 -g 333 xfs; \
    addgroup -g 33 -S www-data; \
    adduser -u 33 -D -S -G www-data www-data ; 
    apk del --no-cache shadow

COPY --from=caddy /usr/bin/caddy /usr/bin/
RUN chmod +x /usr/bin/caddy

COPY --from=dind /usr/local/bin/docker /usr/local/bin/
RUN chmod +x /usr/local/bin/docker

WORKDIR /var/www/docker-aio

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
    chmod 777 -R /root; \
    chown www-data:www-data -R /var/www; \
    rm -r ./php/data; \
    rm -r ./php/session; \
    apk del --no-cache git

RUN mkdir -p /etc/apache2/certs && \
    cd /etc/apache2/certs && \
    openssl req -new -newkey rsa:4096 -days 3650 -nodes -x509 -subj "/C=DE/ST=BE/L=Local/O=Dev/CN=nextcloud.local" -keyout ./ssl.key -out ./ssl.crt;

COPY mastercontainer.conf /etc/apache2/sites-available/

RUN sed -i \
		-e 's/^#\(LoadModule .*mod_rewrite.so\)/\1/' \
		-e 's/^#\(LoadModule .*mod_headers.so\)/\1/' \
		-e 's/^#\(LoadModule .*mod_env.so\)/\1/' \
		-e 's/^#\(LoadModule .*mod_mime.so\)/\1/' \
		-e 's/^#\(LoadModule .*mod_dir.so\)/\1/' \
		-e 's/^#\(LoadModule .*mod_authz_core.so\)/\1/' \
		-e 's/^#\(LoadModule .*mod_mpm_event.so\)/\1/' \
		-e 's/\(LoadModule .*mod_mpm_worker.so\)/#\1/' \
		-e 's/\(LoadModule .*mod_mpm_prefork.so\)/#\1/' \
		/etc/apache2/httpd.conf

RUN sed -i "/^Listen /d" /etc/apache2/httpd.conf
RUN echo "ServerName localhost" | tee -a /etc/apache2/httpd.conf

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
