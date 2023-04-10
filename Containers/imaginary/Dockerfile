FROM golang:1.20.3-alpine3.17 as go
RUN apk add --no-cache \
        vips-dev \
        build-base; \
    go install github.com/h2non/imaginary@b632dae

FROM alpine:3.17.3
RUN apk add --no-cache \
        tzdata \
        ca-certificates \
        netcat-openbsd \
        vips-dev

COPY --from=go /go/bin/imaginary /usr/local/bin/imaginary

# https://github.com/h2non/imaginary#memory-issues
ENV MALLOC_ARENA_MAX=2
ENTRYPOINT ["imaginary", "-p", "9000", "-return-size", "-max-allowed-resolution", "222.2"]

HEALTHCHECK CMD nc -z localhost 9000 || exit 1
LABEL com.centurylinklabs.watchtower.monitor-only="true"
