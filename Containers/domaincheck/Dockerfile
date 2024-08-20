# syntax=docker/dockerfile:latest
FROM alpine:3.20.2
RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache bash lighttpd netcat-openbsd; \
    adduser -S www-data -G www-data; \
    rm -rf /etc/lighttpd/lighttpd.conf; \
    chmod 777 -R /etc/lighttpd; \
    mkdir -p /var/www/domaincheck; \
    chown www-data:www-data -R /var/www; \
    chmod 777 -R /var/www/domaincheck
COPY --chown=www-data:www-data lighttpd.conf /lighttpd.conf

COPY --chmod=775 start.sh /start.sh

USER www-data
ENTRYPOINT ["/start.sh"]

HEALTHCHECK CMD nc -z 127.0.0.1 $APACHE_PORT || exit 1
LABEL com.centurylinklabs.watchtower.enable="false"
