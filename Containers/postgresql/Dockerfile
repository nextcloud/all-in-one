# From https://github.com/docker-library/postgres/blob/master/13/alpine/Dockerfile
FROM postgres:13-alpine

RUN apk add --update --no-cache bash openssl shadow

# We need to use the same gid and uid as on old installations
RUN set -ex; \
    deluser postgres; \
    groupmod -g 9999 ping; \
    addgroup -g 999 -S postgres; \
    adduser -u 999 -S -D -G postgres -H -h /var/lib/postgresql -s /bin/sh postgres

COPY start.sh /usr/bin/
RUN chmod +x /usr/bin/start.sh

RUN mkdir /mnt/data; \
    chown postgres:postgres /mnt/data;

VOLUME /mnt/data

# Give root a random password
RUN echo "root:$(openssl rand -base64 12)" | chpasswd

USER postgres
ENTRYPOINT ["start.sh"]
