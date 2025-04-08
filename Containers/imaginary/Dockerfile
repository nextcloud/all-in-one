# syntax=docker/dockerfile:latest
FROM golang:1.24.2-alpine3.21 AS go

ENV IMAGINARY_HASH=1d4e251cfcd58ea66f8361f8721d7b8cc85002a3

RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache \
        vips-dev \
        vips-magick \
        vips-heif \
        vips-jxl \
        vips-poppler \
        build-base; \
    go install github.com/h2non/imaginary@"$IMAGINARY_HASH";

FROM alpine:3.21.3
RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache \
        tzdata \
        ca-certificates \
        netcat-openbsd \
        vips \
        vips-magick \
        vips-heif \
        vips-jxl \
        vips-poppler \
        ttf-dejavu \
        bash

COPY --from=go /go/bin/imaginary /usr/local/bin/imaginary
COPY --chmod=775 start.sh /start.sh
COPY --chmod=775 healthcheck.sh /healthcheck.sh

ENV PORT=9000

USER 65534

# https://github.com/h2non/imaginary#memory-issues
ENV MALLOC_ARENA_MAX=2
ENTRYPOINT ["/start.sh"]

HEALTHCHECK CMD /healthcheck.sh
LABEL com.centurylinklabs.watchtower.enable="false"
