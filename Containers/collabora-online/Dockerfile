# syntax=docker/dockerfile:latest
# From https://gitlab.collabora.com/collabora-online/docker
# hadolint ignore=DL3007
FROM registry.gitlab.collabora.com/collabora-online/docker:latest

USER root
ARG DEBIAN_FRONTEND=noninteractive

COPY --chmod=775 healthcheck.sh /healthcheck.sh

USER 1001

HEALTHCHECK --start-period=60s --retries=9 CMD /healthcheck.sh
LABEL com.centurylinklabs.watchtower.enable="false" \
    org.label-schema.vendor="Nextcloud"
