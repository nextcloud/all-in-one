# Probably from this file: https://github.com/Cisco-Talos/clamav-docker/blob/main/clamav/1.0/alpine/Dockerfile
FROM clamav/clamav:1.0.1-2

COPY clamav.conf /tmp/clamav.conf

RUN apk add --no-cache tzdata; \
    tee -a /etc/clamav/clamd.conf < /tmp/clamav.conf
LABEL com.centurylinklabs.watchtower.monitor-only="true"
