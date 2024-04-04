# syntax=docker/dockerfile:latest
FROM golang:1.22.2-alpine3.18 as go

ENV IMAGINARY_HASH 6cd9edd1d3fb151eb773c14552886e4fc8e50138

RUN set -ex; \
    apk add --no-cache \
        vips-dev \
        vips-magick \
        vips-heif \
        vips-jxl \
        vips-poppler \
        build-base; \
    go install github.com/h2non/imaginary@"$IMAGINARY_HASH";

FROM alpine:3.18.6
RUN set -ex; \
    apk add --no-cache \
        tzdata \
        ca-certificates \
        netcat-openbsd \
        vips \
        vips-magick \
        vips-heif \
        vips-jxl \
        vips-poppler \
        bash

COPY --from=go /go/bin/imaginary /usr/local/bin/imaginary
COPY --chmod=775 start.sh /start.sh

ENV PORT 9000

USER nobody

# https://github.com/h2non/imaginary#memory-issues
ENV MALLOC_ARENA_MAX=2
ENTRYPOINT ["/start.sh"]

HEALTHCHECK CMD nc -z localhost "$PORT" || exit 1
LABEL com.centurylinklabs.watchtower.enable="false"
