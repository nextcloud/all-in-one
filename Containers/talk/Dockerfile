FROM alpine:3.17.2
USER root

COPY --from=nats:2.9.14-scratch /nats-server /usr/local/bin/nats-server
COPY --from=strukturag/nextcloud-spreed-signaling:1.1.1 /usr/bin/nextcloud-spreed-signaling /usr/local/bin/signaling

RUN apk add --no-cache ca-certificates tzdata bash \
                       coturn \
                       openssl \
                       supervisor \
                       bind-tools \
                       netcat-openbsd; \
    apk add --no-cache janus-gateway --repository http://dl-cdn.alpinelinux.org/alpine/edge/testing; \
    apk add --no-cache shadow; \
    useradd --system talk; \
    apk del --no-cache shadow;

# Give root a random password
RUN echo "root:$(openssl rand -base64 12)" | chpasswd

COPY --chmod=775 start.sh /usr/bin/
COPY --chmod=664 supervisord.conf /
RUN for sampleconf in /etc/janus/*.sample; do mv "$sampleconf" "$(echo $sampleconf | sed "s|.sample||g")"; done

RUN wget https://raw.githubusercontent.com/rxi/json.lua/master/json.lua -O /usr/share/janus/lua/json.lua; \
    wget https://raw.githubusercontent.com/kikito/ansicolors.lua/master/ansicolors.lua -O /usr/share/janus/lua/ansicolors.lua

RUN touch /etc/nats.conf \
          /etc/signaling.conf \
          /etc/turnserver.conf; \
    echo "listen: 127.0.0.1:4222" | tee /etc/nats.conf; \
    mkdir -p /var/tmp \
             /var/lib/turn \
             /var/log/supervisord \
             /var/run/supervisord; \
    chown talk:talk -R /usr \
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
CMD ["/usr/bin/supervisord", "-c", "/supervisord.conf"]

HEALTHCHECK CMD (nc -z localhost 8081 && nc -z localhost 8188 && nc -z localhost 4222 && nc -z localhost $TALK_PORT) || exit 1
LABEL com.centurylinklabs.watchtower.monitor-only="true"
