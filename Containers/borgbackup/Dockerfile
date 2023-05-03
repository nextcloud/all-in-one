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

COPY --chmod=775 start.sh /usr/bin/start.sh
COPY --chmod=775 backupscript.sh /backupscript.sh

ENTRYPOINT ["start.sh"]
LABEL com.centurylinklabs.watchtower.monitor-only="true"
