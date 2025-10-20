# syntax=docker/dockerfile:latest
FROM alpine:3.22.2

RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache tzdata clamav clamav-milter supervisor bash; \
    mkdir -p /var/lib/clamav /run/clamav /var/log/supervisord /var/run/supervisord; \
    chmod 777 -R /run/clamav /var/log/clamav /var/log/supervisord /var/run/supervisord; \
    chown -R 100:100 /var/lib/clamav; \
    sed -i "s|#\?MaxDirectoryRecursion.*|MaxDirectoryRecursion 30|g" /etc/clamav/clamd.conf; \
    sed -i "s|#\?MaxScanSize.*|MaxScanSize 2G|g" /etc/clamav/clamd.conf; \
    sed -i "s|#\?MaxFileSize.*|MaxFileSize 2G|g" /etc/clamav/clamd.conf; \
    sed -i "s|#\?PCREMaxFileSize.*|PCREMaxFileSize 0|g" /etc/clamav/clamd.conf; \
    sed -i "s|#\?StreamMaxLength.*|StreamMaxLength aio-placeholder|g" /etc/clamav/clamd.conf; \
    sed -i "s|#\?TCPSocket|TCPSocket|g" /etc/clamav/clamd.conf; \
    sed -i "s|^LocalSocket .*|LocalSocket /tmp/clamd.sock|g" /etc/clamav/clamd.conf; \
    sed -i "s|Example| |g" /etc/clamav/clamav-milter.conf; \
    sed -i "s|#\?MilterSocket inet:7357|MilterSocket inet:7357|g" /etc/clamav/clamav-milter.conf; \
    sed -i "s|#\?ClamdSocket unix:/run/clamav/clamd.sock|ClamdSocket unix:/tmp/clamd.sock|g" /etc/clamav/clamav-milter.conf; \
    sed -i "s|#\?AddHeader Replace|AddHeader Add|g" /etc/clamav/clamav-milter.conf; \
    sed -i "s|#\?Foreground yes|Foreground yes|g" /etc/clamav/clamav-milter.conf

COPY --chmod=775 start.sh /start.sh
COPY --chmod=775 healthcheck.sh /healthcheck.sh
COPY --chmod=664 supervisord.conf /supervisord.conf

USER 100
RUN set -ex; \
    freshclam --foreground --stdout
VOLUME /var/lib/clamav
ENTRYPOINT ["/start.sh"]
CMD ["/usr/bin/supervisord", "-c", "/supervisord.conf"]
LABEL com.centurylinklabs.watchtower.enable="false" \
    org.label-schema.vendor="Nextcloud"
HEALTHCHECK --start-period=60s --retries=9 CMD /healthcheck.sh
