# From https://github.com/containrrr/watchtower/blob/main/dockerfiles/Dockerfile.self-contained
FROM containrrr/watchtower:latest as watchtower

FROM debian:bullseye

RUN set -ex; \
    \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
    ; \
    rm -rf /var/lib/apt/lists/*

COPY --from=watchtower /watchtower /

COPY start.sh /
RUN chmod +x /start.sh

USER root
ENTRYPOINT ["/start.sh"]
