# From https://github.com/docker-library/postgres/blob/master/15/alpine/Dockerfile
FROM postgres:15.3-alpine

COPY --chmod=775 start.sh /start.sh
COPY --chmod=775 healthcheck.sh /healthcheck.sh
COPY --chmod=775 init-user-db.sh /docker-entrypoint-initdb.d/init-user-db.sh

RUN set -ex; \
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
    chown -R postgres:postgres "$PGDATA"; \
    \
    mkdir /mnt/data; \
    chown postgres:postgres /mnt/data; \
    \
# Modify conf
    grep -q "#log_checkpoints" /var/lib/postgresql/data/postgresql.conf; \
    sed -i 's|#log_checkpoints.*|log_checkpoints = off|' /var/lib/postgresql/data/postgresql.conf; \
    \
# Give root a random password
    echo "root:$(openssl rand -base64 12)" | chpasswd; \
    apk --no-cache del openssl;

VOLUME /mnt/data

USER postgres
ENTRYPOINT ["/start.sh"]

HEALTHCHECK CMD /healthcheck.sh
LABEL com.centurylinklabs.watchtower.monitor-only="true"
