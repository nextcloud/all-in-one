# syntax=docker/dockerfile:latest
# From a file located probably somewhere here: https://github.com/CollaboraOnline/online/blob/master/docker/from-packages/Dockerfile
FROM collabora/code:25.04.7.3.1

USER root
ARG DEBIAN_FRONTEND=noninteractive

COPY --chmod=775 healthcheck.sh /healthcheck.sh

USER 1001

HEALTHCHECK --start-period=60s --retries=9 CMD /healthcheck.sh
LABEL com.centurylinklabs.watchtower.enable="false" \
    org.label-schema.vendor="Nextcloud"
