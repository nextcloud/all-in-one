FROM haproxy:2.8.2-alpine3.18

USER root
ENV NEXTCLOUD_HOST nextcloud-aio-nextcloud
RUN set -ex; \
    apk add --no-cache \
        ca-certificates \
        tzdata \
        bind-tools; \
    chmod -R 777 /tmp

COPY --chmod=775 *.sh /
COPY --chmod=664 haproxy.cfg /haproxy.cfg

ENTRYPOINT ["/start.sh"]
HEALTHCHECK CMD /healthcheck.sh
LABEL com.centurylinklabs.watchtower.enable="false"
