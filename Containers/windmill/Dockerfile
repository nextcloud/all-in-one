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
        supervisor \
        tzdata \
        netcat-openbsd; \
    rm -rf /var/lib/apt/lists/*; \
    \
    # Create a single non-root windmill user (uid=10001) that owns both PostgreSQL
    # and Windmill processes — no root or privilege-switching needed at runtime
    groupadd -r windmill --gid=10001; \
    useradd -r -g windmill --uid=10001 --home-dir=/var/lib/windmill --shell=/sbin/nologin windmill; \
    \
    # Create required directories and give windmill user full ownership
    mkdir -p \
        /var/lib/postgresql/data \
        /var/run/postgresql \
        /var/log/supervisord \
        /var/run/supervisord \
        /tmp/windmill/cache \
        /var/lib/windmill; \
    chown -R windmill:windmill \
        /var/lib/postgresql \
        /var/run/postgresql \
        /var/log/supervisord \
        /var/run/supervisord \
        /tmp/windmill/cache \
        /var/lib/windmill; \
    chmod 750 /var/run/postgresql; \
    chmod 750 /var/log/supervisord /var/run/supervisord; \
    \
    # Create symlinks so postgres tools are on PATH
    ln -sf /usr/lib/postgresql/17/bin/postgres /usr/local/bin/postgres; \
    ln -sf /usr/lib/postgresql/17/bin/initdb   /usr/local/bin/initdb; \
    ln -sf /usr/lib/postgresql/17/bin/pg_ctl   /usr/local/bin/pg_ctl

COPY --chmod=775 start.sh /start.sh
COPY --chmod=775 healthcheck.sh /healthcheck.sh
COPY --chmod=775 windmill-start.sh /windmill-start.sh
COPY --chmod=664 supervisord.conf /supervisord.conf

VOLUME ["/var/lib/postgresql/data", "/tmp/windmill/cache"]

USER 10001

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
