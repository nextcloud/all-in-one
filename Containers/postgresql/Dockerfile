# From https://github.com/docker-library/postgres/blob/master/13/bullseye/Dockerfile
FROM postgres:13-bullseye

RUN set -ex; \
    \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        openssl \
    ; \
    rm -rf /var/lib/apt/lists/*

COPY start.sh /usr/bin/
RUN chmod +x /usr/bin/start.sh

RUN mkdir /mnt/data; \
    chown postgres:postgres /mnt/data;

VOLUME /mnt/data

# Give root a random password
RUN echo "root:$(openssl rand -base64 12)" | chpasswd

USER postgres
ENTRYPOINT ["start.sh"]
