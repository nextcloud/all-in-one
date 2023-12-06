FROM golang:1.21.5-alpine3.18 as go

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

FROM alpine:3.18.5
RUN set -ex; \
    apk add --no-cache \
        tzdata \
        ca-certificates \
        netcat-openbsd \
        vips \
        vips-magick \
        vips-heif \
        vips-jxl \
        vips-poppler

COPY --from=go /go/bin/imaginary /usr/local/bin/imaginary

ENV PORT 9000

USER nobody

# https://github.com/h2non/imaginary#memory-issues
ENV MALLOC_ARENA_MAX=2
ENTRYPOINT ["imaginary", "-return-size", "-max-allowed-resolution", "222.2"]

HEALTHCHECK CMD nc -z localhost "$PORT" || exit 1
LABEL com.centurylinklabs.watchtower.enable="false"
