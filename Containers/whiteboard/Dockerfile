# syntax=docker/dockerfile:latest
FROM ghcr.io/nextcloud-releases/whiteboard:v1.0.4

USER root
RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache bash
USER 65534

COPY --chmod=775 start.sh /start.sh

ENTRYPOINT ["/start.sh"]

LABEL com.centurylinklabs.watchtower.enable="false"
