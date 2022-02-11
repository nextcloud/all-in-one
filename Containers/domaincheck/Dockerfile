FROM alpine:3.15.0
RUN apk add --update --no-cache lighttpd bash

RUN adduser -S www-data -G www-data
RUN rm -rf /etc/lighttpd/lighttpd.conf
COPY lighttpd.conf /etc/lighttpd/lighttpd.conf
RUN chmod +r -R /etc/lighttpd && \
    chown www-data:www-data -R /var/www

COPY start.sh /
RUN chmod +x /start.sh

USER www-data
RUN mkdir -p /var/www/domaincheck/
ENTRYPOINT ["/start.sh"]
