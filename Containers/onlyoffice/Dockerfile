# syntax=docker/dockerfile:latest
# From https://github.com/ONLYOFFICE/Docker-DocumentServer/blob/master/Dockerfile
FROM onlyoffice/documentserver:9.3.1.2

# USER root is probably used

COPY --chmod=775 healthcheck.sh /healthcheck.sh

HEALTHCHECK --start-period=60s --retries=9 CMD /healthcheck.sh
LABEL com.centurylinklabs.watchtower.enable="false" \
    wud.watch="false" \
    org.opencontainers.image.title="OnlyOffice for Nextcloud AIO" \
    org.opencontainers.image.description="OnlyOffice Document Server for Nextcloud All-in-One" \
    org.opencontainers.image.url="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.source="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.vendor="Nextcloud" \
    org.opencontainers.image.documentation="https://github.com/nextcloud/all-in-one/blob/main/readme.md"
