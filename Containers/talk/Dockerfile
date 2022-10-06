FROM ubuntu:focal-20220922

RUN set -ex; \
    \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        openssl \
        coturn \
        supervisor \
        curl \
        ca-certificates \
        netcat \
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

RUN curl -sL -o "/usr/share/janus/lua/json.lua" "https://raw.githubusercontent.com/rxi/json.lua/master/json.lua"; \
    curl -sL -o "/usr/share/janus/lua/ansicolors.lua" "https://raw.githubusercontent.com/kikito/ansicolors.lua/master/ansicolors.lua"

RUN mkdir -p /etc/nats; \
    echo "listen: 127.0.0.1:4222" > /etc/nats/nats.conf; \
    chown talk:talk /etc; \
    chown talk:talk -R /etc/nats; \
    chown talk:talk -R /etc/janus; \
    chown talk:talk -R /etc/signaling; \
    chown talk:talk -R /usr

# Give root a random password
RUN echo "root:$(openssl rand -base64 12)" | chpasswd

# Set default talk port https://github.com/nextcloud/all-in-one/issues/1011
ENV TALK_PORT=3478

USER talk
ENTRYPOINT ["start.sh"]
CMD ["/usr/bin/supervisord", "-c", "/supervisord.conf"]

HEALTHCHECK CMD (nc -z localhost 8081 && nc -z localhost 8188 && nc -z localhost 4222 && nc -z localhost $TALK_PORT) || exit 1