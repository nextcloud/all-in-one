# syntax=docker/dockerfile:latest
FROM ghcr.io/nextcloud-releases/whiteboard:v1.0.1

COPY --chmod=775 start.sh /start.sh

ENTRYPOINT ["/start.sh"]

LABEL com.centurylinklabs.watchtower.enable="false"
