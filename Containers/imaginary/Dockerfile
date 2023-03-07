# From https://github.com/h2non/imaginary/blob/master/Dockerfile
FROM nextcloud/imaginary:20230301

USER root
RUN set -ex; \
    \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        netcat \
    ; \
    echo "deb http://deb.debian.org/debian bookworm main" > /etc/apt/sources.list.d/bookworm.list; \
    apt-get update; \
    apt-get install -t bookworm -y --no-install-recommends \
        libheif1 \
        libde265-0 \
        libx265-199 \
        libvips \
    ; \
    rm /etc/apt/sources.list.d/bookworm.list; \
    rm -rf /var/lib/apt/lists/*
USER nobody

ENTRYPOINT ["/usr/local/bin/imaginary", "-return-size", "-max-allowed-resolution", "222.2"]

HEALTHCHECK CMD nc -z localhost 9000 || exit 1
LABEL com.centurylinklabs.watchtower.monitor-only="true"
