# Caddy is a requirement
FROM caddy:2.6.2-alpine as caddy

FROM debian:bullseye-20230109-slim

RUN mkdir -p /mnt/data; \
    chown www-data:www-data /mnt/data;

VOLUME /mnt/data

RUN set -ex; \
    \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        apache2 \
        supervisor \
        wget \
        ca-certificates \
        openssl \
        netcat \
        dpkg-dev \
    ; \
    rm -rf /var/lib/apt/lists/*

COPY --from=caddy /usr/bin/caddy /usr/bin/
RUN chmod +x /usr/bin/caddy

RUN a2enmod rewrite \
    headers \
    proxy \
    proxy_fcgi \
    setenvif \
    env \
    mime \
    dir \
    authz_core \
    alias

COPY nextcloud.conf /etc/apache2/sites-available/

RUN rm /etc/apache2/ports.conf; \
    sed -s -i -e "s/Include ports.conf//" /etc/apache2/apache2.conf; \
    sed -i "/^Listen /d" /etc/apache2/apache2.conf

RUN set -ex; \
    a2dissite 000-default && \
    a2dissite default-ssl && \
    rm -f /etc/apache2/sites-enabled/000-default.conf && \
    rm -f /etc/apache2/sites-enabled/default-ssl.conf && \
    rm /etc/apache2/sites-available/000-default.conf && \
    rm /etc/apache2/sites-available/default-ssl.conf && \
    a2ensite nextcloud.conf && \
    rm -rf /var/www/html/* && \
    chown www-data:www-data -R /var/log/apache2; \
    mkdir -p /var/run/apache2; \
    chown -R www-data:www-data /var/run/apache2; \
    chown -R www-data:www-data /var/www;

RUN mkdir /var/log/supervisord; \
    mkdir /var/run/supervisord; \
    chown www-data:www-data /var/run/supervisord; \
    chown www-data:www-data /var/log/supervisord;

COPY Caddyfile /

COPY start.sh /usr/bin/
COPY healthcheck.sh /usr/bin/
COPY supervisord.conf /
RUN chmod +x /usr/bin/start.sh; \
    chmod +x /usr/bin/healthcheck.sh; \
    chmod +r /supervisord.conf; \
    chown www-data:www-data /Caddyfile; \
    chmod +r -R /etc/apache2

# Give root a random password
RUN echo "root:$(openssl rand -base64 12)" | chpasswd

USER www-data

ENTRYPOINT ["start.sh"]
CMD ["/usr/bin/supervisord", "-c", "/supervisord.conf"]

HEALTHCHECK CMD healthcheck.sh