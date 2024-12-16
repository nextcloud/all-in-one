# syntax=docker/dockerfile:latest
FROM alpine:3.21.0

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
LABEL com.centurylinklabs.watchtower.enable="false"
