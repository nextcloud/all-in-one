# syntax=docker/dockerfile:latest
# Probably from this file: https://github.com/Cisco-Talos/clamav-docker/blob/main/clamav/1.3/alpine/Dockerfile
FROM clamav/clamav:1.4.1-11

COPY clamav.conf /clamav.conf
COPY --chmod=775 start.script /start.script

RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache tzdata bash; \
    mkdir -p /var/run/clamav /run/lock; \
    chown -R clamav:clamav /var/run/clamav /run/clamav /var/log/clamav /var/lock /run/lock; \
    chmod 777 -R /var/run/clamav /run/clamav /var/log/clamav /var/lock /run/lock /tmp; \
    sed -i "/^set -eu/r /start.script" /init-unprivileged; \
    rm /start.script; \
    grep -q 'clamd --foreground &' /init-unprivileged; \
    sed -i "s|clamd --foreground \&|clamd --foreground --config-file /tmp/clamd.conf \&|" /init-unprivileged; \
    cat /init-unprivileged

VOLUME /var/lib/clamav

USER 100

LABEL com.centurylinklabs.watchtower.enable="false"

ENTRYPOINT ["/init-unprivileged"]
