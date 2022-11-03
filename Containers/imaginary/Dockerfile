# From https://github.com/h2non/imaginary/blob/master/Dockerfile
FROM nextcloud/imaginary:20221101

USER root
RUN set -ex; \
    \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        netcat \
    ; \
    rm -rf /var/lib/apt/lists/*
USER nobody

HEALTHCHECK CMD nc -z localhost 9000 || exit 1