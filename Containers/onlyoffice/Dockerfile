# syntax=docker/dockerfile:latest
# From https://github.com/ONLYOFFICE/Docker-DocumentServer/blob/master/Dockerfile
FROM onlyoffice/documentserver:9.3.1.2

# USER root is probably used

COPY --chmod=775 healthcheck.sh /healthcheck.sh

HEALTHCHECK --start-period=60s --retries=9 CMD ["/healthcheck.sh"]
LABEL com.centurylinklabs.watchtower.enable="false" \
    wud.watch="false" \
    dockhand.update="false" \
    org.opencontainers.image.title="OnlyOffice for Nextcloud AIO" \
    org.opencontainers.image.description="OnlyOffice Document Server for Nextcloud All-in-One" \
    org.opencontainers.image.url="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.source="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.vendor="Nextcloud" \
    org.opencontainers.image.documentation="https://github.com/nextcloud/all-in-one/blob/main/readme.md"

# Block requests to external sources other than our Nextcloud instance, in order to mitigate the risk of
# attacks on the brotli-vulnerability in nodejs via responses to such external requests.
# This should be removed when OnlyOffice publishes updated container/binary versions that use fixed versions
# of nodejs.
RUN echo '{ "externalRequest": { "action": { "allow": false } } }' > /etc/onlyoffice/documentserver/local-production-linux.json
