# syntax=docker/dockerfile:latest
FROM golang:1.25.5-alpine3.22 AS go

ENV WATCHTOWER_COMMIT_HASH=1ee8747544ce9a49711d9314f1690b30c29e6a8c	

RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache \
        build-base; \
    go install github.com/nicholas-fedor/watchtower@$WATCHTOWER_COMMIT_HASH # v1.12.5

FROM alpine:3.22.2

RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache bash ca-certificates tzdata

COPY --from=go /go/bin/watchtower /watchtower

COPY --chmod=775 start.sh /start.sh

# hadolint ignore=DL3002
USER root

ENTRYPOINT ["/start.sh"]
LABEL com.centurylinklabs.watchtower.enable="false" \
    org.label-schema.vendor="Nextcloud"
