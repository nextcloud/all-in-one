FROM nats:2.9.18-scratch as nats
FROM strukturag/nextcloud-spreed-signaling:1.1.2 as signaling
FROM coturn/coturn:4.6.2-r3-alpine
USER root

COPY --from=nats /nats-server /usr/local/bin/nats-server
COPY --from=signaling /usr/bin/nextcloud-spreed-signaling /usr/local/bin/nextcloud-spreed-signaling

COPY --chmod=775 start.sh /usr/bin/start.sh
COPY --chmod=664 supervisord.conf /supervisord.conf

RUN set -ex; \
    apk add --no-cache \
        ca-certificates \
        tzdata \
        bash \
        janus-gateway \
        openssl \
        supervisor \
        bind-tools \
        netcat-openbsd \
        shadow \
        util-linux \
        build-base \
        lua5.3-dev \
        luarocks5.3; \
    useradd --system talk; \
    luarocks-5.3 install luajson; \
    luarocks-5.3 install ansicolors; \
    rename -v ".jcfg.sample" ".jcfg" /etc/janus/*.sample; \
    apk del --no-cache \
        shadow \
        util-linux \
        build-base \
        lua5.3-dev \
        luarocks5.3; \
    \
# Give root a random password
    echo "root:$(openssl rand -base64 12)" | chpasswd; \
    \
    touch \
        /etc/nats.conf \
        /etc/signaling.conf \
        /etc/turnserver.conf; \
    echo "listen: 127.0.0.1:4222" | tee /etc/nats.conf; \
    mkdir -p \
        /var/tmp \
        /var/lib/turn \
        /var/log/supervisord \
        /var/run/supervisord; \
    chown talk:talk -R \
        /usr \
        /etc/janus \
        /etc/nats.conf \
        /etc/signaling.conf \
        /etc/turnserver.conf \
        /var/lib/turn \
        /var/log/supervisord \
        /var/run/supervisord;

# Set default talk port https://github.com/nextcloud/all-in-one/issues/1011
ENV TALK_PORT=3478

USER talk
ENTRYPOINT ["start.sh"]
CMD ["supervisord", "-c", "/supervisord.conf"]

HEALTHCHECK CMD (nc -z localhost 8081 && nc -z localhost 8188 && nc -z localhost 4222 && nc -z localhost "$TALK_PORT" && nc -z "$NC_DOMAIN" "$TALK_PORT") || exit 1
LABEL com.centurylinklabs.watchtower.monitor-only="true"
