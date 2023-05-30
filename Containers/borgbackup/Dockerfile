FROM alpine:3.17.3

RUN set -ex; \
    \
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

LABEL com.centurylinklabs.watchtower.monitor-only="true"
ENV BORG_RETENTION_POLICY="--keep-within=7d --keep-weekly=4 --keep-monthly=6"
