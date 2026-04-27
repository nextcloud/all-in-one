# syntax=docker/dockerfile:latest
# Stage 1: PostgreSQL server from the official Debian bookworm image
# (matches the Debian bookworm base used by windmill-labs/windmill)
FROM postgres:17-bookworm AS postgres-base

# Final stage: derive from the official Windmill image and bundle PostgreSQL
FROM ghcr.io/windmill-labs/windmill:main

USER root

ARG DEBIAN_FRONTEND=noninteractive

# Copy PostgreSQL server binaries, libraries, and utilities from the postgres stage
COPY --from=postgres-base /usr/lib/postgresql /usr/lib/postgresql
COPY --from=postgres-base /usr/share/postgresql /usr/share/postgresql
COPY --from=postgres-base /usr/bin/pg_dump \
                          /usr/bin/pg_dumpall \
                          /usr/bin/pg_restore \
                          /usr/bin/

# Install supervisor from standard Debian repos (remove broken external sources first)
# hadolint ignore=DL3008
RUN set -ex; \
    rm -f \
        /etc/apt/sources.list.d/nodesource.sources \
        /etc/apt/sources.list.d/pgdg.list; \
    apt-get update; \
    apt-get upgrade -y; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        supervisor \
        tzdata \
        netcat-openbsd; \
    rm -rf /var/lib/apt/lists/*; \
    update-ca-certificates; \
    # Copy the CA bundle to a world-readable path so uid=1000 can access it.
    # /etc/ssl/certs/ is persistently mode 700 in the base image's layer and
    # cannot be chmod'd in a derived image, so we copy the regenerated bundle.
    cp /etc/ssl/certs/ca-certificates.crt /etc/ssl/ca-bundle.crt; \
    chmod 644 /etc/ssl/ca-bundle.crt; \
    \
    # The base image already ships a 'windmill' user/group at uid/gid 1000.
    # Create the directories our services need and hand them to that user.
    # /tmp/windmill/cache is intentionally excluded: the base image pre-creates
    # it as 777 so any UID can write to it; a recursive chown on ~340 MB of
    # pre-installed runtimes would create a huge and unnecessary image layer.
    mkdir -p \
        /var/lib/postgresql/data \
        /var/run/postgresql \
        /var/log/supervisord \
        /var/run/supervisord; \
    chown -R windmill:windmill \
        /var/lib/postgresql \
        /var/run/postgresql \
        /var/log/supervisord \
        /var/run/supervisord; \
    chmod 1777 \
        /var/run/postgresql \
        /var/log/supervisord \
        /var/run/supervisord; \
    \
    # Create symlinks so postgres tools are on PATH
    ln -sf /usr/lib/postgresql/17/bin/postgres /usr/local/bin/postgres; \
    ln -sf /usr/lib/postgresql/17/bin/initdb   /usr/local/bin/initdb; \
    ln -sf /usr/lib/postgresql/17/bin/pg_ctl   /usr/local/bin/pg_ctl

COPY --chmod=775 start.sh /start.sh
COPY --chmod=775 healthcheck.sh /healthcheck.sh
COPY --chmod=775 windmill-start.sh /windmill-start.sh
COPY --chmod=664 supervisord.conf /supervisord.conf

# Redirect writable tool dirs to the persistent cache volume so the
# container can run with a read-only root filesystem.
# HOME is already correct (/home/windmill) for the pre-existing uid=1000 user.
# WINDMILL_DIR is set to the cache volume so Windmill's logs/worker dirs
# are inside the volume and writable (Docker creates the volume mount-point
# parent /tmp/windmill as root:root, making /tmp/windmill/logs inaccessible
# for uid=1000 when /tmp is a tmpfs).
ENV UV_TOOL_BIN_DIR=/tmp/windmill/cache/uv/bin \
    UV_TOOL_DIR=/tmp/windmill/cache/uv/tools \
    WINDMILL_DIR=/tmp/windmill/cache \
    SSL_CERT_FILE=/etc/ssl/ca-bundle.crt

VOLUME ["/var/lib/postgresql/data", "/tmp/windmill/cache"]

# Use the pre-existing windmill user (uid=1000) from the base image
USER 1000

EXPOSE 8000

ENTRYPOINT ["/start.sh"]

HEALTHCHECK CMD /healthcheck.sh
LABEL com.centurylinklabs.watchtower.enable="false" \
    wud.watch="false" \
    org.opencontainers.image.title="Windmill for Nextcloud AIO" \
    org.opencontainers.image.description="Windmill workflow engine with bundled PostgreSQL for Nextcloud All-in-One" \
    org.opencontainers.image.url="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.source="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.vendor="Nextcloud" \
    org.opencontainers.image.documentation="https://github.com/nextcloud/all-in-one/blob/main/readme.md"
