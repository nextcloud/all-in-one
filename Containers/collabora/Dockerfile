# syntax=docker/dockerfile:latest
# From a file located probably somewhere here: https://github.com/CollaboraOnline/online/tree/master/docker
FROM collabora/code:24.04.13.1.1

USER root
ARG DEBIAN_FRONTEND=noninteractive

# hadolint ignore=DL3008
RUN set -ex; \
    \
    apt-get update; \
    apt-get install -y --no-install-recommends \
#        # Disable because seems to be failing currently
#        # tzdata \
        netcat-openbsd \
    ; \
    rm -rf /var/lib/apt/lists/*;

COPY --chmod=775 healthcheck.sh /healthcheck.sh

USER 100

HEALTHCHECK --start-period=60s --retries=9 CMD /healthcheck.sh
LABEL com.centurylinklabs.watchtower.enable="false"
