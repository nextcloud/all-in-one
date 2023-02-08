# Probably from here https://github.com/elastic/elasticsearch/blob/main/distribution/docker/src/docker/Dockerfile
FROM elasticsearch:7.17.9

RUN elasticsearch-plugin install --batch ingest-attachment

RUN set -ex; \
    \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        tzdata \
    ; \
    rm -rf /var/lib/apt/lists/*

COPY start.sh /

RUN chmod +x /start.sh
ENTRYPOINT ["/bin/tini", "--", "/start.sh"]

HEALTHCHECK CMD nc -z localhost 9200 || exit 1
LABEL com.centurylinklabs.watchtower.monitor-only="true"
