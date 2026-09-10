# syntax=docker/dockerfile:latest
FROM alpine:3.24.1

COPY --chmod=775 start.sh /start.sh
COPY --chmod=775 healthcheck.sh /healthcheck.sh

SHELL ["/bin/ash", "-o", "pipefail", "-c"]
RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache \
        ca-certificates \
        netcat-openbsd \
        tzdata \
        bash; \
    delgroup www-data; \
    addgroup -g 33 -S www-data; \
    adduser -u 33 -S -D -G www-data -H -s /sbin/nologin www-data;

USER www-data:www-data
ENTRYPOINT ["/start.sh"]

HEALTHCHECK CMD ["/healthcheck.sh"]
LABEL com.centurylinklabs.watchtower.enable="false" \
    wud.watch="false" \
    dockhand.update="false" \
    org.opencontainers.image.title="Notify Push for Nextcloud AIO" \
    org.opencontainers.image.description="Nextcloud notify_push high-performance backend for Nextcloud All-in-One" \
    org.opencontainers.image.url="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.source="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.vendor="Nextcloud" \
    org.opencontainers.image.documentation="https://github.com/nextcloud/all-in-one/blob/main/readme.md"
