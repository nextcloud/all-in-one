# syntax=docker/dockerfile:latest
FROM alpine:3.19.1

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
        jq

VOLUME /root

COPY --chmod=770 *.sh /

ENTRYPOINT ["/start.sh"]
# hadolint ignore=DL3002
USER root

LABEL com.centurylinklabs.watchtower.enable="false"
ENV BORG_RETENTION_POLICY="--keep-within=7d --keep-weekly=4 --keep-monthly=6"
