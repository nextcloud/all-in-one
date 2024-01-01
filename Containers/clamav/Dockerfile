# Probably from this file: https://github.com/Cisco-Talos/clamav-docker/blob/main/clamav/1.1/alpine/Dockerfile
FROM clamav/clamav:1.2.1-24

COPY clamav.conf /tmp/clamav.conf

RUN set -ex; \
    apk add --no-cache tzdata; \
    cat /tmp/clamav.conf >> /etc/clamav/clamd.conf; \
    rm /tmp/clamav.conf; \
    mkdir -p /var/run/clamav /run/lock; \
    chown -R clamav:clamav /var/run/clamav /run/clamav /var/log/clamav /var/lock /run/lock; \
    chmod 777 -R /var/run/clamav /run/clamav /var/log/clamav /var/lock /run/lock /tmp

VOLUME /var/lib/clamav

USER clamav

LABEL com.centurylinklabs.watchtower.enable="false"
