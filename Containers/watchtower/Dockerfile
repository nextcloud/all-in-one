# syntax=docker/dockerfile:latest
FROM golang:1.26.4-alpine3.24 AS go

ENV WATCHTOWER_COMMIT_HASH=1e4e4da323b3ba61d41b7f078f405b02ebe87bef	

RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache \
        build-base; \
    go install github.com/nicholas-fedor/watchtower@$WATCHTOWER_COMMIT_HASH # v1.18.1

FROM alpine:3.24.1

RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache bash ca-certificates tzdata

COPY --from=go /go/bin/watchtower /watchtower

COPY --chmod=775 start.sh /start.sh

# hadolint ignore=DL3002
USER root

ENV AIO_LOG_LEVEL="warn"

ENTRYPOINT ["/start.sh"]
LABEL com.centurylinklabs.watchtower.enable="false" \
    wud.watch="false" \
    dockhand.update="false" \
    org.opencontainers.image.title="Watchtower for Nextcloud AIO" \
    org.opencontainers.image.description="Watchtower auto-update service for Nextcloud All-in-One containers" \
    org.opencontainers.image.url="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.source="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.vendor="Nextcloud" \
    org.opencontainers.image.documentation="https://github.com/nextcloud/all-in-one/blob/main/readme.md"
