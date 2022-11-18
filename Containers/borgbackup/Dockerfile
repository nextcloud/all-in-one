FROM debian:bullseye-20221114-slim

RUN set -ex; \
    \
    echo "deb http://deb.debian.org/debian bullseye-backports main" >> /etc/apt/sources.list; \
    apt-get update; \
    apt-get install -y --no-install-recommends borgbackup -t bullseye-backports; \
    apt-get install -y --no-install-recommends \
        rsync \
        fuse \
        python3-llfuse \
        jq \
    ; \
    rm -rf /var/lib/apt/lists/*

VOLUME /root

COPY start.sh /usr/bin/
COPY backupscript.sh /
RUN chmod +x /usr/bin/start.sh; \
    chmod +x /backupscript.sh

USER root
ENTRYPOINT ["start.sh"]