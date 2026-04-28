# syntax=docker/dockerfile:latest
# From a file located probably somewhere here: https://github.com/CollaboraOnline/online/blob/master/docker/from-packages/Dockerfile
FROM collabora/code:25.04.9.4.1

USER root
ARG DEBIAN_FRONTEND=noninteractive

COPY --chmod=775 start.sh /start.sh
COPY --chmod=775 healthcheck.sh /healthcheck.sh

USER 1001

HEALTHCHECK --start-period=60s --retries=9 CMD /healthcheck.sh
LABEL com.centurylinklabs.watchtower.enable="false" \
    wud.watch="false" \
    org.opencontainers.image.title="Collabora for Nextcloud AIO" \
    org.opencontainers.image.description="Collabora CODE document editor for Nextcloud All-in-One" \
    org.opencontainers.image.url="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.source="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.vendor="Nextcloud" \
    org.opencontainers.image.documentation="https://github.com/nextcloud/all-in-one/blob/main/readme.md"

ENTRYPOINT ["/start.sh"]
