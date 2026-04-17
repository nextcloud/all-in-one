# syntax=docker/dockerfile:latest
FROM alpine:3.23.4

COPY --chmod=775 start.sh /start.sh
COPY --chmod=775 healthcheck.sh /healthcheck.sh

RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache \
        ca-certificates \
        netcat-openbsd \
        tzdata \
        bash \
        openssl; \
# Give root a random password
    echo "root:$(openssl rand -base64 12)" | chpasswd; \
    apk del --no-cache \
        openssl;

USER 33
ENTRYPOINT ["/start.sh"]

HEALTHCHECK CMD /healthcheck.sh
LABEL com.centurylinklabs.watchtower.enable="false" \
    wud.watch="false" \
    org.opencontainers.image.title="Notify Push for Nextcloud AIO" \
    org.opencontainers.image.description="Nextcloud notify_push high-performance backend for Nextcloud All-in-One" \
    org.opencontainers.image.url="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.source="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.vendor="Nextcloud" \
    org.opencontainers.image.documentation="https://github.com/nextcloud/all-in-one/blob/main/readme.md"
