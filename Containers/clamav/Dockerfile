# syntax=docker/dockerfile:latest
FROM alpine:3.21.3

RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache tzdata clamav supervisor; \
    mkdir /run/clamav; \
    chmod 777 -R /run/clamav /var/log/clamav /var/log/supervisord /var/run/supervisord; \
    sed -i "s|#\?MaxDirectoryRecursion.*|MaxDirectoryRecursion 30|g" /etc/clamav/clamd.conf; \
    sed -i "s|#\?MaxFileSize.*|MaxFileSize 2G|g" /etc/clamav/clamd.conf; \
    sed -i "s|#\?PCREMaxFileSize.*|PCREMaxFileSize aio-placeholder|g" /etc/clamav/clamd.conf; \
    sed -i "s|#\?StreamMaxLength.*|StreamMaxLength aio-placeholder|g" /etc/clamav/clamd.conf; \
    sed -i "s|#\?TCPSocket|TCPSocket|g" /etc/clamav/clamd.conf; \
    freshclam --foreground --stdout

COPY --chmod=775 start.sh /start.sh
COPY --chmod=775 healthcheck.sh /healthcheck.sh
COPY --chmod=664 supervisord.conf /supervisord.conf

USER 100
VOLUME /var/lib/clamav
ENTRYPOINT ["/start.sh"]
CMD ["/usr/bin/supervisord", "-c", "/supervisord.conf"]
LABEL com.centurylinklabs.watchtower.enable="false"
HEALTHCHECK --start-period=60s --retries=9 CMD /healthcheck.sh
