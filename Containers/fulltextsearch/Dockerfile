# syntax=docker/dockerfile:latest
# Probably from here https://github.com/elastic/elasticsearch/blob/main/distribution/docker/src/docker/Dockerfile
FROM elasticsearch:8.15.2

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
    rm -rf /var/lib/apt/lists/*; \
    elasticsearch-plugin install --batch ingest-attachment

USER 1000:0

HEALTHCHECK CMD nc -z 127.0.0.1 9200 || exit 1
LABEL com.centurylinklabs.watchtower.enable="false"
