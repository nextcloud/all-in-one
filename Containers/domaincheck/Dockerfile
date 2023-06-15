FROM alpine:3.18.2
RUN set -ex; \
    apk add --no-cache bash lighttpd netcat-openbsd; \
    adduser -S www-data -G www-data; \
    rm -rf /etc/lighttpd/lighttpd.conf; \
    chmod +r -R /etc/lighttpd; \
    mkdir -p /var/www/domaincheck; \
    chown www-data:www-data -R /var/www
COPY --chown=www-data:www-data lighttpd.conf /etc/lighttpd/lighttpd.conf

COPY --chmod=775 start.sh /start.sh

USER www-data
ENTRYPOINT ["/start.sh"]

HEALTHCHECK CMD nc -z localhost $APACHE_PORT || exit 1
LABEL com.centurylinklabs.watchtower.monitor-only="true"
