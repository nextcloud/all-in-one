# syntax=docker/dockerfile:latest
FROM nats:2.14.0-scratch AS nats
FROM eturnal/eturnal:1.12.2-alpine AS eturnal
FROM strukturag/nextcloud-spreed-signaling:2.1.1 AS signaling
FROM alpine:3.23.4 AS janus

ARG JANUS_VERSION=v1.4.1
WORKDIR /src
RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache \
        ca-certificates \
        git \
        autoconf \
        automake \
        build-base \
        pkgconfig \
        libtool \
        util-linux \
        glib-dev \
        zlib-dev \
        openssl-dev \
        jansson-dev \
        libnice-dev \
        libconfig-dev \
        libsrtp-dev \
        libusrsctp-dev \
        gengetopt-dev \
        libwebsockets-dev; \
    git clone --recursive https://github.com/meetecho/janus-gateway --depth=1 --single-branch --branch "$JANUS_VERSION" /src; \
    /src/autogen.sh; \
    /src/configure --disable-rabbitmq --disable-mqtt --disable-boringssl; \
    make; \
    make install; \
    make configs; \
    rename -v ".jcfg.sample" ".jcfg" /usr/local/etc/janus/*.jcfg.sample

FROM alpine:3.23.4
ENV ETURNAL_ETC_DIR="/conf"
ENV SKIP_CERT_VERIFY=false \
    AIO_LOG_LEVEL=warn
COPY --from=janus     --chmod=777 --chown=1000:1000 /usr/local                          /usr/local
COPY --from=eturnal   --chmod=777 --chown=1000:1000 /opt/eturnal                        /opt/eturnal
COPY --from=nats      --chmod=777 --chown=1000:1000 /nats-server                        /usr/local/bin/nats-server
COPY --from=signaling --chmod=777 --chown=1000:1000 /usr/bin/nextcloud-spreed-signaling /usr/local/bin/nextcloud-spreed-signaling

COPY --chmod=775 start.sh /start.sh
COPY --chmod=775 healthcheck.sh /healthcheck.sh
COPY --chmod=664 supervisord.conf /supervisord.conf

RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache \
        ca-certificates \
        tzdata \
        bash \
        openssl \
        supervisor \
        bind-tools \
        netcat-openbsd \
        \
        glib \
        zlib \
        libssl3 \
        libcrypto3 \
        jansson \
        libnice \
        libconfig \
        libsrtp \
        libusrsctp \
        libwebsockets \
        \
        shadow \
        grep \
        util-linux-misc; \
    useradd --system -u 1000 eturnal; \
    apk del --no-cache \
        shadow; \
    \
# Give root a random password
    echo "root:$(openssl rand -base64 12)" | chpasswd; \
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
        /var/log/supervisord \
        /var/run/supervisord \
        /usr/local/lib/janus/loggers; \
    chown eturnal:eturnal -R \
        /etc/nats.conf \
        /var/log/supervisord \
        /var/run/supervisord; \
    chmod 777 -R \
        /tmp \
        /conf \
        /var/run/supervisord \
        /var/log/supervisord; \
    ln -s /opt/eturnal/bin/stun /usr/local/bin/stun; \
    ln -s /opt/eturnal/bin/eturnalctl /usr/local/bin/eturnalctl

USER 1000
ENTRYPOINT ["/start.sh"]
CMD ["supervisord", "-c", "/supervisord.conf"]

HEALTHCHECK CMD /healthcheck.sh
LABEL com.centurylinklabs.watchtower.enable="false" \
    wud.watch="false" \
    org.opencontainers.image.title="Talk for Nextcloud AIO" \
    org.opencontainers.image.description="Nextcloud Talk with NATS, Janus, eturnal, and signaling server for Nextcloud All-in-One" \
    org.opencontainers.image.url="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.source="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.vendor="Nextcloud" \
    org.opencontainers.image.documentation="https://github.com/nextcloud/all-in-one/blob/main/readme.md"
