# syntax=docker/dockerfile:latest
# From https://github.com/docker-library/postgres/blob/master/16/alpine3.21/Dockerfile
FROM postgres:16.8-alpine

COPY --chmod=775 start.sh /start.sh
COPY --chmod=775 healthcheck.sh /healthcheck.sh
COPY --chmod=775 init-user-db.sh /docker-entrypoint-initdb.d/init-user-db.sh

RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache \
        bash \
        openssl \
        shadow \
        grep; \
    \
# We need to use the same gid and uid as on old installations
    deluser postgres; \
    groupmod -g 9999 ping; \
    addgroup -g 999 -S postgres; \
    adduser -u 999 -S -D -G postgres -H -h /var/lib/postgresql -s /bin/sh postgres; \
    apk del --no-cache shadow; \
    \
# Fix default permissions
    chown -R postgres:postgres /var/lib/postgresql; \
    chown -R postgres:postgres /var/run/postgresql; \
    chmod -R 777 /var/run/postgresql; \
    chown -R postgres:postgres "$PGDATA"; \
    \
    mkdir /mnt/data; \
    chown postgres:postgres /mnt/data; \
    \
# Give root a random password
    echo "root:$(openssl rand -base64 12)" | chpasswd; \
    apk --no-cache del openssl; \
    \
# Get rid of unused binaries
    rm -f /usr/local/bin/gosu /usr/local/bin/su-exec;

VOLUME /mnt/data

USER 999
ENTRYPOINT ["/start.sh"]

HEALTHCHECK CMD /healthcheck.sh
LABEL com.centurylinklabs.watchtower.enable="false"
