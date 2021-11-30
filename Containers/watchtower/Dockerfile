# From https://github.com/containrrr/watchtower/blob/main/dockerfiles/Dockerfile.self-contained
FROM containrrr/watchtower:latest as watchtower

FROM debian:bullseye

RUN set -ex; \
    \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        openssl \
    ; \
    rm -rf /var/lib/apt/lists/*

COPY --from=watchtower /watchtower /

COPY start.sh /
RUN chmod +x /start.sh

# Give root a random password
RUN echo "root:$(openssl rand -base64 12)" | chpasswd

# add docker group
RUN groupadd -g 998 docker && \
    usermod -aG docker nobody

USER nobody
ENTRYPOINT ["/start.sh"]
