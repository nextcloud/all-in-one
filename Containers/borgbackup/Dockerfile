FROM alpine:3.17.2

RUN set -ex; \
    \
    apk add --no-cache \
        bash \
        borgbackup \
        rsync \
        fuse \
        py3-llfuse \
        jq

VOLUME /root

COPY start.sh /usr/bin/
COPY backupscript.sh /
RUN chmod +x /usr/bin/start.sh; \
    chmod +x /backupscript.sh

USER root
ENTRYPOINT ["start.sh"]
LABEL com.centurylinklabs.watchtower.monitor-only="true"
