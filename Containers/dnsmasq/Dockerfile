# syntax=docker/dockerfile:latest
FROM alpine:3.21

RUN apk add --no-cache dnsmasq iproute2

COPY --chmod=755 start.sh /start.sh

ENTRYPOINT ["/start.sh"]

LABEL com.centurylinklabs.watchtower.enable="false" \
    wud.watch="false" \
    org.opencontainers.image.title="Dnsmasq for Nextcloud AIO" \
    org.opencontainers.image.description="Lightweight DNS server that resolves NC_DOMAIN to the local server IP for LAN devices" \
    org.opencontainers.image.url="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.source="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.vendor="Nextcloud" \
    org.opencontainers.image.documentation="https://github.com/nextcloud/all-in-one/blob/main/community-containers/dnsmasq/readme.md"
