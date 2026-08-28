# syntax=docker/dockerfile:latest
# From https://github.com/redis/docker-library-redis/blob/release/8.2/alpine/Dockerfile
FROM redis:8.10.1-alpine

COPY --chmod=775 start.sh /start.sh

SHELL ["/bin/ash", "-o", "pipefail", "-c"]
RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache bash

COPY --chmod=775 healthcheck.sh /healthcheck.sh

USER redis:redis
ENTRYPOINT ["/start.sh"]

HEALTHCHECK CMD ["/healthcheck.sh"]
LABEL com.centurylinklabs.watchtower.enable="false" \
    wud.watch="false" \
    dockhand.update="false" \
    org.opencontainers.image.title="Redis for Nextcloud AIO" \
    org.opencontainers.image.description="Redis cache server for Nextcloud All-in-One" \
    org.opencontainers.image.url="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.source="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.vendor="Nextcloud" \
    org.opencontainers.image.documentation="https://github.com/nextcloud/all-in-one/blob/main/readme.md"
