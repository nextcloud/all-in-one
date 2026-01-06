# syntax=docker/dockerfile:latest
FROM haproxy:3.3.1-alpine

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
    org.label-schema.vendor="Nextcloud"
