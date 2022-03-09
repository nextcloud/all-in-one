FROM alpine:3.15.0

RUN set -ex; \
    \
    apk add --update --no-cache \
        bash \
        borgbackup \
        rsync \
        fuse3 \
        py3-pip \
        pip3 install pyfuse3

VOLUME /root

COPY start.sh /usr/bin/
COPY backupscript.sh /
RUN chmod +x /usr/bin/start.sh; \
    chmod +x /backupscript.sh

USER root
ENTRYPOINT ["start.sh"]