# From https://github.com/h2non/imaginary/blob/master/Dockerfile
FROM nextcloud/imaginary:20220919

USER root
RUN set -ex; \
    \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
    ; \
    rm -rf /var/lib/apt/lists/*
USER nobody

HEALTHCHECK CMD curl -skI 127.0.0.1:9000 || exit 1