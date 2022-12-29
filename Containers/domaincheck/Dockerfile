FROM alpine:3.16.3
RUN apk add --update --no-cache lighttpd bash netcat-openbsd

RUN adduser -S www-data -G www-data
RUN rm -rf /etc/lighttpd/lighttpd.conf
COPY lighttpd.conf /etc/lighttpd/lighttpd.conf
RUN chmod +r -R /etc/lighttpd && \
    chown www-data:www-data -R /var/www && \
    chown www-data:www-data /etc/lighttpd/lighttpd.conf

COPY start.sh /
RUN chmod +x /start.sh

USER www-data
RUN mkdir -p /var/www/domaincheck/
ENTRYPOINT ["/start.sh"]

HEALTHCHECK CMD nc -z localhost $APACHE_PORT || exit 1