# syntax=docker/dockerfile:latest
# Probably from here https://github.com/elastic/elasticsearch/blob/main/distribution/docker/src/docker/Dockerfile
FROM elasticsearch:8.16.1

USER root

ARG DEBIAN_FRONTEND=noninteractive

# hadolint ignore=DL3008
RUN set -ex; \
    \
    apt-get update; \
    apt-get upgrade -y; \
    apt-get install -y --no-install-recommends \
        tzdata \
    ; \
    rm -rf /var/lib/apt/lists/*;

COPY --chmod=775 healthcheck.sh /healthcheck.sh

USER 1000:0

HEALTHCHECK --interval=10s --timeout=5s --start-period=1m --retries=5 CMD /healthcheck.sh
LABEL com.centurylinklabs.watchtower.enable="false"
