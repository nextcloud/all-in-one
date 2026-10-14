# syntax=docker/dockerfile:latest
FROM ghcr.io/euro-office/documentserver:v9.3.5

# USER root is probably used

# The base image ships a Postgres datadir that was never shut down, so every container
# start runs crash recovery and can outlast the 60s pg_ctl timeout on slow storage.
# Running the recovery once here leaves a cleanly stopped datadir in the image.
# Can be removed once https://github.com/Euro-Office/DocumentServer/issues/180 is fixed upstream.
RUN service postgresql start && service postgresql stop

COPY --chmod=775 healthcheck.sh /healthcheck.sh

HEALTHCHECK --start-period=60s --retries=9 CMD ["/healthcheck.sh"]
LABEL com.centurylinklabs.watchtower.enable="false" \
    wud.watch="false" \
    dockhand.update="false" \
    org.opencontainers.image.title="EuroOffice for Nextcloud AIO" \
    org.opencontainers.image.description="EuroOffice Document Server for Nextcloud All-in-One" \
    org.opencontainers.image.url="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.source="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.vendor="Nextcloud" \
    org.opencontainers.image.documentation="https://github.com/nextcloud/all-in-one/blob/main/readme.md"
