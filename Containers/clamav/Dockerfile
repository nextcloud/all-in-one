# syntax=docker/dockerfile:latest
# Probably from this file: https://github.com/Cisco-Talos/clamav-docker/blob/main/clamav/1.3/alpine/Dockerfile
FROM clamav/clamav:1.3.1-59

COPY clamav.conf /clamav.conf
COPY --chmod=775 start.sh /start.sh

RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache tzdata bash; \
    mkdir -p /var/run/clamav /run/lock; \
    chown -R clamav:clamav /var/run/clamav /run/clamav /var/log/clamav /var/lock /run/lock; \
    chmod 777 -R /var/run/clamav /run/clamav /var/log/clamav /var/lock /run/lock /tmp

VOLUME /var/lib/clamav

USER clamav

LABEL com.centurylinklabs.watchtower.enable="false"

ENTRYPOINT ["/start.sh"]
