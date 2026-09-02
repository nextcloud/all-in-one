# syntax=docker/dockerfile:latest
# From a file located probably somewhere here: https://github.com/CollaboraOnline/online/blob/master/docker/from-packages/Dockerfile
FROM collabora/code:26.04.3.2.1

# The base image ships no shell anymore, so no start.sh wrapper can be used here.
# ENTRYPOINT, USER and HEALTHCHECK are inherited from it on purpose.

LABEL com.centurylinklabs.watchtower.enable="false" \
    wud.watch="false" \
    dockhand.update="false" \
    org.opencontainers.image.title="Collabora for Nextcloud AIO" \
    org.opencontainers.image.description="Collabora CODE document editor for Nextcloud All-in-One" \
    org.opencontainers.image.url="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.source="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.vendor="Nextcloud" \
    org.opencontainers.image.documentation="https://github.com/nextcloud/all-in-one/blob/main/readme.md"
