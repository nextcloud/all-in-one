FROM debian:bullseye-slim

RUN set -ex; \
    \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        borgbackup \
        rsync \
        fuse \
        python3-llfuse \
    ; \
    rm -rf /var/lib/apt/lists/*

VOLUME /root

COPY start.sh /usr/bin/
COPY backupscript.sh /
RUN chmod +x /usr/bin/start.sh; \
    chmod +x /backupscript.sh

USER root
ENTRYPOINT ["start.sh"]