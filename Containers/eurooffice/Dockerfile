# syntax=docker/dockerfile:latest
FROM ghcr.io/euro-office/documentserver:v9.3.3

# USER root is probably used

COPY --chmod=775 healthcheck.sh /healthcheck.sh
# Make sdkjs-plugins writable so it can be mounted as a persistent named
# volume (upstream image ships it read-only, dr-xr-xr-x, owned by ds:ds)
RUN chown ds:ds /var/www/euro-office/documentserver/sdkjs-plugins \
    && chmod 755 /var/www/euro-office/documentserver/sdkjs-plugins

HEALTHCHECK --start-period=60s --retries=9 CMD ["/healthcheck.sh"]
LABEL com.centurylinklabs.watchtower.enable="false" \
    wud.watch="false" \
    dockhand.update="false" \
    org.opencontainers.image.title="EuroOffice for Nextcloud AIO" \
    org.opencontainers.image.description="EuroOffice Document Server for Nextcloud All-in-One" \
    org.opencontainers.image.url="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.source="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.vendor="Nextcloud" \
    org.opencontainers.image.documentation="https://github.com/nextcloud/all-in-one/blob/main/readme.md"
