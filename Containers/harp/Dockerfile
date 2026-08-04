# syntax=docker/dockerfile:latest
FROM ghcr.io/nextcloud/nextcloud-appapi-harp:v0.4.3

# USER root is probably used

LABEL com.centurylinklabs.watchtower.enable="false" \
    wud.watch="false" \
    dockhand.update="false" \
    org.opencontainers.image.title="Nextcloud AppAPI HARP for Nextcloud AIO" \
    org.opencontainers.image.description="Nextcloud AppAPI HARP for Nextcloud All-in-One" \
    org.opencontainers.image.url="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.source="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.vendor="Nextcloud" \
    org.opencontainers.image.documentation="https://github.com/nextcloud/all-in-one/blob/main/readme.md"

# Copied from upstream
HEALTHCHECK --interval=10s --timeout=10s --retries=9 CMD /healthcheck.sh
