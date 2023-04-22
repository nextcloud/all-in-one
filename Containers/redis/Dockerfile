# From https://github.com/docker-library/redis/blob/master/7.0/alpine/Dockerfile
FROM redis:7.0.11-alpine

RUN apk add --no-cache openssl bash

COPY start.sh /usr/bin/
RUN chmod +x /usr/bin/start.sh

# Give root a random password
RUN echo "root:$(openssl rand -base64 12)" | chpasswd

USER redis
ENTRYPOINT ["start.sh"]

HEALTHCHECK CMD redis-cli -a $REDIS_HOST_PASSWORD PING || exit 1
LABEL com.centurylinklabs.watchtower.monitor-only="true"
