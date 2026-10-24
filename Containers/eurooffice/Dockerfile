# syntax=docker/dockerfile:latest
FROM ghcr.io/euro-office/documentserver:v9.3.5

# USER root is probably used

RUN set -ex; \
    export DEBIAN_FRONTEND=noninteractive; \
    apt-get update; \
    apt-get upgrade -y; \
    rm -rf /var/lib/apt/lists/*

COPY --chmod=775 healthcheck.sh /healthcheck.sh

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
