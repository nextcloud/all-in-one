# Probably from here https://github.com/elastic/elasticsearch/blob/main/distribution/docker/src/docker/Dockerfile
FROM elasticsearch:7.17.8

RUN elasticsearch-plugin install --batch ingest-attachment

RUN set -ex; \
    \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        tzdata \
    ; \
    rm -rf /var/lib/apt/lists/*

HEALTHCHECK CMD nc -z localhost 9200 || exit 1
