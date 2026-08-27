# syntax=docker/dockerfile:latest
FROM nats:2.14.5-scratch AS nats
FROM eturnal/eturnal:1.12.2-alpine AS eturnal
FROM strukturag/nextcloud-spreed-signaling:2.1.1 AS signaling
FROM alpine:3.24.1 AS janus

RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache janus-gateway; \
    mv /etc/janus/janus.transport.websockets.jcfg.sample /etc/janus/janus.transport.websockets.jcfg; \
    mv /etc/janus/janus.plugin.videoroom.jcfg.sample /etc/janus/janus.plugin.videoroom.jcfg; \
    sed -i '/^room-1234:/,/^}/d' /etc/janus/janus.plugin.videoroom.jcfg

FROM alpine:3.24.1
ENV ETURNAL_ETC_DIR="/conf"
ENV SKIP_CERT_VERIFY=false \
    AIO_LOG_LEVEL=warn
COPY --from=janus     --chmod=777 --chown=1000:1000 /usr/bin/janus /usr/bin/janus
COPY --from=janus     --chmod=777 --chown=1000:1000 /usr/lib/janus/plugins/libjanus_videoroom.so* /usr/lib/janus/plugins/
COPY --from=janus     --chmod=777 --chown=1000:1000 /usr/lib/janus/transports/libjanus_websockets.so* /usr/lib/janus/transports/
COPY --from=janus     --chmod=777 --chown=1000:1000 /etc/janus/janus.jcfg.sample /etc/janus/janus.plugin.videoroom.jcfg /etc/janus/janus.transport.websockets.jcfg /etc/janus/

COPY --from=eturnal   --chmod=777 --chown=1000:1000 /opt/eturnal                        /opt/eturnal
COPY --from=nats      --chmod=777 --chown=1000:1000 /nats-server                        /usr/local/bin/nats-server
COPY --from=signaling --chmod=777 --chown=1000:1000 /usr/bin/nextcloud-spreed-signaling /usr/local/bin/nextcloud-spreed-signaling

COPY --chmod=775 start.sh /start.sh
COPY --chmod=775 healthcheck.sh /healthcheck.sh
COPY dinit.d/ /etc/dinit.d/

SHELL ["/bin/ash", "-o", "pipefail", "-c"]
RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache \
        ca-certificates \
        tzdata \
        bash \
        openssl \
        dinit \
        bind-tools \
        netcat-openbsd \
        \
        glib \
        libconfig \
        libnice \
        jansson \
        libusrsctp \
        libsrtp \
        libcurl \
        libwebsockets \
        \
        shadow \
        grep \
        util-linux-misc; \
    useradd --system -u 1000 eturnal; \
    apk del --no-cache \
        shadow; \
    \
    touch \
        /etc/nats.conf \
        /etc/eturnal.yml; \
# write_deadline: "10s" — without a write deadline, a lagging subscriber can stall the broker indefinitely, blocking all other signaling messages.
# max_payload: 8MB — the default is 1 MB; signaling payloads in large meetings (many participants, ICE candidates) can exceed this, causing dropped messages.
    printf 'listen: 127.0.0.1:4222\nwrite_deadline: "10s"\nmax_payload: 8MB\n' | tee /etc/nats.conf; \
    mkdir -p \
        /var/tmp \
        /conf \
        /var/lib/turn \
        /usr/lib/janus/loggers; \
    chown eturnal:eturnal -R \
        /etc/nats.conf; \
    chmod 777 -R \
        /tmp \
        /conf; \
    ln -s /opt/eturnal/bin/stun /usr/local/bin/stun; \
    ln -s /opt/eturnal/bin/eturnalctl /usr/local/bin/eturnalctl

USER eturnal:eturnal
ENTRYPOINT ["/start.sh"]
CMD ["dinit", "--system", "--container", "--socket-path", "/tmp/dinitctl", "nats-server", "eturnal", "janus", "signaling"]

HEALTHCHECK CMD ["/healthcheck.sh"]
LABEL com.centurylinklabs.watchtower.enable="false" \
    wud.watch="false" \
    dockhand.update="false" \
    org.opencontainers.image.title="Talk for Nextcloud AIO" \
    org.opencontainers.image.description="Nextcloud Talk with NATS, Janus, eturnal, and signaling server for Nextcloud All-in-One" \
    org.opencontainers.image.url="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.source="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.vendor="Nextcloud" \
    org.opencontainers.image.documentation="https://github.com/nextcloud/all-in-one/blob/main/readme.md"
