# syntax=docker/dockerfile:latest
# From a file located probably somewhere here: https://github.com/CollaboraOnline/online/blob/master/docker/from-packages/Dockerfile
FROM collabora/code:25.04.2.1.1

USER root
ARG DEBIAN_FRONTEND=noninteractive

# hadolint ignore=DL3008
RUN set -ex; \
    \
    apt-get update; \
    apt-get install --fix-broken -y --no-install-recommends \
        netcat-openbsd \
    ; \
    rm -rf /var/lib/apt/lists/*;

COPY --chmod=775 healthcheck.sh /healthcheck.sh

USER 1001

HEALTHCHECK --start-period=60s --retries=9 CMD /healthcheck.sh
LABEL com.centurylinklabs.watchtower.enable="false" \
    org.label-schema.vendor="Nextcloud"
