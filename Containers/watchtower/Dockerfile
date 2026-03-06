# syntax=docker/dockerfile:latest
FROM golang:1.26.1-alpine3.23 AS go

ENV WATCHTOWER_COMMIT_HASH=2a3fe10ad86f36a7f208105bbe1fb29e51caac5b	

RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache \
        build-base; \
    go install github.com/nicholas-fedor/watchtower@$WATCHTOWER_COMMIT_HASH # v1.14.3

FROM alpine:3.23.3

RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache bash ca-certificates tzdata

COPY --from=go /go/bin/watchtower /watchtower

COPY --chmod=775 start.sh /start.sh

# hadolint ignore=DL3002
USER root

ENTRYPOINT ["/start.sh"]
LABEL com.centurylinklabs.watchtower.enable="false" \
    wud.watch="false" \
    org.label-schema.vendor="Nextcloud"
