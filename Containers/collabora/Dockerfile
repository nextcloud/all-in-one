# From a file located probably somewhere here: https://github.com/CollaboraOnline/online/tree/master/docker
FROM collabora/code:23.05.4.1.1

USER root

RUN set -ex; \
    \
    apt-get update; \
    export DEBIAN_FRONTEND=noninteractive; \
    apt-get install -y --no-install-recommends \
        tzdata \
        netcat-openbsd \
    ; \
    rm -rf /var/lib/apt/lists/*;

USER 100

HEALTHCHECK CMD nc -z localhost 9980 || exit 1
LABEL com.centurylinklabs.watchtower.enable="false"
