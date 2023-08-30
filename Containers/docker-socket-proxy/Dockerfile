FROM haproxy:2.8.2-alpine3.18

USER root
RUN apk add --no-cache ca-certificates tzdata bind-tools

COPY --chmod=775 start.sh /start.sh
COPY --chmod=664 haproxy.cfg /conf/haproxy.cfg

ENTRYPOINT ["/start.sh"]
HEALTHCHECK CMD [ "$(wget http://127.0.0.1:2375/v1.41/_ping -qO -)" = "OK" ] || exit 1
