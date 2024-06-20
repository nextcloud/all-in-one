# syntax=docker/dockerfile:latest
# From https://github.com/containrrr/watchtower/blob/main/dockerfiles/Dockerfile.self-contained
FROM containrrr/watchtower:1.7.1 as watchtower

FROM alpine:3.20.1

RUN apk upgrade --no-cache -a; \
    apk add --no-cache bash

COPY --from=watchtower /watchtower /watchtower

COPY --chmod=775 start.sh /start.sh

# hadolint ignore=DL3002
USER root

ENTRYPOINT ["/start.sh"]
LABEL com.centurylinklabs.watchtower.enable="false"
