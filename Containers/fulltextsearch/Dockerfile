# syntax=docker/dockerfile:latest
# Probably from here https://github.com/elastic/dockerfiles/blob/9.3/elasticsearch/Dockerfile
FROM elasticsearch:9.3.4

USER root

# hadolint ignore=DL3041
RUN set -ex; \
    \
    microdnf update -y; \
    microdnf install -y --setopt=tsflags=nodocs \
        tzdata \
    ; \
    microdnf clean all;

COPY --chmod=775 start.sh /start.sh
COPY --chmod=775 healthcheck.sh /healthcheck.sh

USER 1000:0

HEALTHCHECK --interval=10s --timeout=5s --start-period=1m --retries=5 CMD /healthcheck.sh
LABEL com.centurylinklabs.watchtower.enable="false" \
    wud.watch="false" \
    org.opencontainers.image.title="Full Text Search for Nextcloud AIO" \
    org.opencontainers.image.description="Elasticsearch-based full-text search for Nextcloud All-in-One" \
    org.opencontainers.image.url="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.source="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.vendor="Nextcloud" \
    org.opencontainers.image.documentation="https://github.com/nextcloud/all-in-one/blob/main/readme.md"
ENV ES_JAVA_OPTS="-Xms512M -Xmx512M"

ENTRYPOINT ["/start.sh"]
