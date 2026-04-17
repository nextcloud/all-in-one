# syntax=docker/dockerfile:latest
FROM haproxy:3.3.6-alpine

# hadolint ignore=DL3002
USER root
ENV NEXTCLOUD_HOST=nextcloud-aio-nextcloud
RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache \
        ca-certificates \
        tzdata \
        bash \
        bind-tools; \
    chmod -R 777 /tmp

COPY --chmod=775 *.sh /
COPY --chmod=664 haproxy.cfg /haproxy.cfg

ENTRYPOINT ["/start.sh"]
HEALTHCHECK CMD /healthcheck.sh
LABEL com.centurylinklabs.watchtower.enable="false" \
    wud.watch="false" \
    org.opencontainers.image.title="Docker Socket Proxy for Nextcloud AIO" \
    org.opencontainers.image.description="HAProxy-based Docker socket proxy for Nextcloud All-in-One" \
    org.opencontainers.image.url="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.source="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.vendor="Nextcloud" \
    org.opencontainers.image.documentation="https://github.com/nextcloud/all-in-one/blob/main/readme.md"
