# syntax=docker/dockerfile:latest
FROM alpine:3.23.4

RUN set -ex; \
    \
    apk upgrade --no-cache -a; \
    apk add --no-cache \
        util-linux-misc \
        bash \
        borgbackup \
        rsync \
        fuse \
        py3-llfuse \
        jq \
        openssh-client

VOLUME /root

COPY --chmod=770 *.sh /
COPY borg_excludes /

ENTRYPOINT ["/start.sh"]
# hadolint ignore=DL3002
USER root

LABEL com.centurylinklabs.watchtower.enable="false" \
    wud.watch="false" \
    org.opencontainers.image.title="Borgbackup for Nextcloud AIO" \
    org.opencontainers.image.description="BorgBackup-based backup service for Nextcloud All-in-One" \
    org.opencontainers.image.url="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.source="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.vendor="Nextcloud" \
    org.opencontainers.image.documentation="https://github.com/nextcloud/all-in-one/blob/main/readme.md"
ENV BORG_RETENTION_POLICY="--keep-within=7d --keep-weekly=4 --keep-monthly=6"
