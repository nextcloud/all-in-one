FROM ubuntu:focal-20220302

EXPOSE 3478

RUN set -ex; \
    \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        openssl \
        coturn \
        supervisor \
        curl \
        ca-certificates \
    ; \
    rm -rf /var/lib/apt/lists/*

RUN set -ex; \
    curl -sL -o "/etc/apt/trusted.gpg.d/morph027-nats-server.asc" "https://packaging.gitlab.io/nats-server/gpg.key"; \
    echo "deb https://packaging.gitlab.io/nats-server nats main" > /etc/apt/sources.list.d/morph027-nats-server.list; \
    . /etc/lsb-release; \
    curl -sL -o "/etc/apt/trusted.gpg.d/morph027-janus.asc" "https://packaging.gitlab.io/janus/gpg.key"; \
    echo "deb https://packaging.gitlab.io/janus/$DISTRIB_CODENAME $DISTRIB_CODENAME main" > /etc/apt/sources.list.d/morph027-janus.list; \
    curl -sL -o "/etc/apt/trusted.gpg.d/morph027-nextcloud-spreed-signaling.asc" "https://packaging.gitlab.io/nextcloud-spreed-signaling/gpg.key"; \
    echo "deb https://packaging.gitlab.io/nextcloud-spreed-signaling signaling main" > /etc/apt/sources.list.d/morph027-nextcloud-spreed-signaling.list

RUN set -ex; \
    \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        nats-server \
        janus \
        nextcloud-spreed-signaling \
    ; \
    rm -rf /var/lib/apt/lists/*

RUN adduser --system --group talk

RUN mkdir /var/log/supervisord; \
    mkdir /var/run/supervisord; \
    chown talk:talk /var/run/supervisord; \
    chown talk:talk /var/log/supervisord;

COPY start.sh /usr/bin/
COPY supervisord.conf /
RUN chmod +x /usr/bin/start.sh; \
    chmod +r /supervisord.conf; \
    touch /etc/turnserver.conf; \
    chown talk:talk /etc/turnserver.conf; \
    sed -i '/TURNSERVER_ENABLED/c\TURNSERVER_ENABLED=1' /etc/default/coturn; \
    mkdir -p /var/tmp;

RUN mkdir -p /etc/nats; \
    echo "listen: 127.0.0.1:4222" > /etc/nats/nats.conf; \
    chown talk:talk -R /etc/nats; \
    chown talk:talk -R /etc/janus; \
    chown talk:talk -R /etc/signaling; \
    chown talk:talk -R /usr/share/janus

# Give root a random password
RUN echo "root:$(openssl rand -base64 12)" | chpasswd

USER talk
ENTRYPOINT ["start.sh"]
CMD ["/usr/bin/supervisord", "-c", "/supervisord.conf"]
