# syntax=docker/dockerfile:latest
# Probably from here https://github.com/elastic/elasticsearch/blob/main/distribution/docker/src/docker/Dockerfile
FROM elasticsearch:8.12.1

USER root

# hadolint ignore=DL3008
RUN set -ex; \
    \
    export DEBIAN_FRONTEND=noninteractive; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        tzdata \
    ; \
    rm -rf /var/lib/apt/lists/*; \
    elasticsearch-plugin install --batch ingest-attachment

USER 1000:0

HEALTHCHECK CMD nc -z localhost 9200 || exit 1
LABEL com.centurylinklabs.watchtower.enable="false"
