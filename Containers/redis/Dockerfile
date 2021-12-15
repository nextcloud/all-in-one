# From https://github.com/docker-library/redis/blob/master/6.2/Dockerfile
FROM redis:6.2-bullseye

RUN set -ex; \
    \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        openssl \
    ; \
    rm -rf /var/lib/apt/lists/*

COPY start.sh /usr/bin/
RUN chmod +x /usr/bin/start.sh

# Give root a random password
RUN echo "root:$(openssl rand -base64 12)" | chpasswd

USER redis
ENTRYPOINT ["start.sh"]
