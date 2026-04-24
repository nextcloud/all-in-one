# syntax=docker/dockerfile:latest
FROM ghcr.io/linuxserver/ddclient:latest

# Auto-configure ddclient for deSEC when NC_DOMAIN and DESEC_TOKEN are provided.
# The linuxserver base image executes all scripts in /custom-cont-init.d/ before
# the main service starts, which lets us generate ddclient.conf without any manual step.
COPY --chmod=755 ddclient-config-gen.sh /custom-cont-init.d/ddclient-config-gen.sh

LABEL com.centurylinklabs.watchtower.enable="false" \
    wud.watch="false" \
    org.opencontainers.image.title="DDclient for Nextcloud AIO" \
    org.opencontainers.image.description="DDclient with automatic deSEC configuration for Nextcloud All-in-One" \
    org.opencontainers.image.url="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.source="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.vendor="Nextcloud" \
    org.opencontainers.image.documentation="https://github.com/nextcloud/all-in-one/blob/main/community-containers/ddclient/readme.md"
