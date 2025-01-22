# syntax=docker/dockerfile:latest
# Probably from this file: https://github.com/nextcloud/whiteboard/blob/main/Dockerfile
FROM ghcr.io/nextcloud-releases/whiteboard:v1.0.5

USER root
RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache bash
USER 65534

COPY --chmod=775 start.sh /start.sh
COPY --chmod=775 healthcheck.sh /healthcheck.sh

HEALTHCHECK CMD /healthcheck.sh

ENTRYPOINT ["/start.sh"]

LABEL com.centurylinklabs.watchtower.enable="false"
