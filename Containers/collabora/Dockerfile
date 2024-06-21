# syntax=docker/dockerfile:latest
# From a file located probably somewhere here: https://github.com/CollaboraOnline/online/tree/master/docker
FROM collabora/code:24.04.4.2.1

USER root
ARG DEBIAN_FRONTEND=noninteractive

# hadolint ignore=DL3008
RUN set -ex; \
    \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        tzdata \
        netcat-openbsd \
    ; \
    rm -rf /var/lib/apt/lists/*;

USER 100

HEALTHCHECK CMD nc -z 127.0.0.1 9980 || exit 1
LABEL com.centurylinklabs.watchtower.enable="false"
