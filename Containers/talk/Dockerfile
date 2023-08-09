FROM nats:2.9.21-scratch as nats
FROM strukturag/nextcloud-spreed-signaling:1.1.3 as signaling
FROM alpine:3.18.3 as janus

ARG JANUS_VERSION=v0.14.0
WORKDIR /src
RUN set -ex; \
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

FROM coturn/coturn:4.6.2-alpine3.18
USER root

RUN set -ex; \
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
        shadow; \
    useradd --system talk; \
    apk del --no-cache \
        shadow; \
    \
# Give root a random password
    echo "root:$(openssl rand -base64 12)" | chpasswd; \
    \
    touch \
        /etc/nats.conf; \
    echo "listen: 127.0.0.1:4222" | tee /etc/nats.conf; \
    mkdir -p \
        /var/tmp \
        /conf \
        /var/lib/turn \
        /var/log/supervisord \
        /var/run/supervisord \
        /usr/local/lib/janus/loggers; \
    chown talk:talk -R \
        /usr \
        /etc/nats.conf \
        /var/lib/turn \
        /var/log/supervisord \
        /var/run/supervisord; \
    chmod 777 -R \
        /tmp \
        /conf \
        /var/run/supervisord \
        /var/lib/turn \
        /var/log/supervisord;

COPY --from=janus /usr/local /usr/local
COPY --from=nats /nats-server /usr/local/bin/nats-server
COPY --from=signaling /usr/bin/nextcloud-spreed-signaling /usr/local/bin/nextcloud-spreed-signaling

COPY --chmod=775 start.sh /start.sh
COPY --chmod=664 supervisord.conf /supervisord.conf

# Set default talk port https://github.com/nextcloud/all-in-one/issues/1011
ENV TALK_PORT=3478

USER talk
ENTRYPOINT ["/start.sh"]
CMD ["supervisord", "-c", "/supervisord.conf"]

HEALTHCHECK CMD (nc -z localhost 8081 && nc -z localhost 8188 && nc -z localhost 4222 && nc -z localhost "$TALK_PORT" && nc -z "$NC_DOMAIN" "$TALK_PORT") || exit 1
LABEL com.centurylinklabs.watchtower.enable="false"
