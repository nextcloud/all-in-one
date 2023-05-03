# Probably from this file: https://github.com/Cisco-Talos/clamav-docker/blob/main/clamav/1.1/alpine/Dockerfile
FROM clamav/clamav:1.1.0-1

COPY clamav.conf /tmp/clamav.conf

RUN set -ex; \
    apk add --no-cache tzdata; \
    cat /tmp/clamav.conf | tee -a /etc/clamav/clamd.conf; \
    rm /tmp/clamav.conf
LABEL com.centurylinklabs.watchtower.monitor-only="true"
