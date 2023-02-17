# From https://github.com/docker-library/postgres/blob/master/15/alpine/Dockerfile
FROM postgres:15.2-alpine

RUN apk add --no-cache bash openssl shadow grep mawk

# We need to use the same gid and uid as on old installations
RUN set -ex; \
    deluser postgres; \
    groupmod -g 9999 ping; \
    addgroup -g 999 -S postgres; \
    adduser -u 999 -S -D -G postgres -H -h /var/lib/postgresql -s /bin/sh postgres

# Fix default permissions
RUN set -ex; \
    chown -R postgres:postgres /var/lib/postgresql; \
    chown -R postgres:postgres /var/run/postgresql; \
    chown -R postgres:postgres "$PGDATA"

COPY start.sh /usr/bin/
COPY healthcheck.sh /usr/bin/
COPY init-user-db.sh /docker-entrypoint-initdb.d/
RUN set -ex; \
    chmod +x /usr/bin/start.sh; \
    chmod +xr /docker-entrypoint-initdb.d/init-user-db.sh; \
    chmod +x /usr/bin/healthcheck.sh

RUN mkdir /mnt/data; \
    chown postgres:postgres /mnt/data;

VOLUME /mnt/data

# Give root a random password
RUN echo "root:$(openssl rand -base64 12)" | chpasswd

USER postgres
ENTRYPOINT ["start.sh"]

HEALTHCHECK CMD healthcheck.sh
LABEL com.centurylinklabs.watchtower.monitor-only="true"
