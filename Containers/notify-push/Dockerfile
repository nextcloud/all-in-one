FROM alpine:3.18.2

COPY --chmod=775 start.sh /start.sh

RUN set -ex; \
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

HEALTHCHECK CMD nc -z localhost 7867 || exit 1
LABEL com.centurylinklabs.watchtower.enable="false"
