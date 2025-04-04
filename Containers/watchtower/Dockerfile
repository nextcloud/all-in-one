# syntax=docker/dockerfile:latest
FROM golang:1.24.2-alpine3.21 AS go

RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache \
        build-base; \
    go install github.com/containrrr/watchtower@76f9cea516593fabb8ca91ff13de55caa6aa0a8b;

FROM alpine:3.21.3

RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache bash ca-certificates tzdata

COPY --from=go /go/bin/watchtower /watchtower

COPY --chmod=775 start.sh /start.sh

# hadolint ignore=DL3002
USER root

ENTRYPOINT ["/start.sh"]
LABEL com.centurylinklabs.watchtower.enable="false"
