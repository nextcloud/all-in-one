FROM alpine:3.15.0

RUN set -ex; \
    \
    apk add --update --no-cache \
        bash \
        borgbackup \
        rsync \
        fuse

Run set -ex; \
    \
    echo "https://dl-cdn.alpinelinux.org/alpine/edge/testing" >> /etc/apk/repositories \
    apk add --update --no-cache py3-llfuse

VOLUME /root

COPY start.sh /usr/bin/
COPY backupscript.sh /
RUN chmod +x /usr/bin/start.sh; \
    chmod +x /backupscript.sh

USER root
ENTRYPOINT ["start.sh"]