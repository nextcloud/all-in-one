#!/bin/bash
set -e

# Validate required environment variables
if [ -z "$BASE_URL" ]; then
    echo "BASE_URL must be provided. Exiting!"
    exit 1
fi

export TZ="${TZ:-Etc/UTC}"

# Clear the cache volume when the image has been updated.
# /etc/windmill-image-build-epoch is written at image build time.
# A copy is stored in the cache volume after first start.
# If the two differ the image was updated and any stale cached artefacts
# (uv tools, worker dirs) should be removed so Windmill starts clean.
IMAGE_EPOCH_FILE="/etc/windmill-image-build-epoch"
CACHE_EPOCH_FILE="/tmp/windmill/cache/.image-build-epoch"
if [ -f "$IMAGE_EPOCH_FILE" ]; then
    IMAGE_EPOCH="$(cat "$IMAGE_EPOCH_FILE")"
    if [ -f "$CACHE_EPOCH_FILE" ]; then
        CACHE_EPOCH="$(cat "$CACHE_EPOCH_FILE")"
        if [ "$IMAGE_EPOCH" != "$CACHE_EPOCH" ]; then
            echo "Windmill image updated (was $CACHE_EPOCH, now $IMAGE_EPOCH). Clearing cache..."
            find /tmp/windmill/cache -mindepth 1 -maxdepth 1 ! -name '.image-build-epoch' -exec rm -rf {} +
        fi
    fi
    echo "$IMAGE_EPOCH" > "$CACHE_EPOCH_FILE"
fi

PGDATA="/var/lib/postgresql/data"

# ── Automatic PostgreSQL major-version upgrade ────────────────────────────────
# The image records the current PG major version in /etc/postgres-major-version
# at build time.  On each start we compare that against the version stored in
# $PGDATA/PG_VERSION (written by initdb).  When the image ships a newer major
# version we run pg_upgrade automatically; the old binaries are kept in the
# image for exactly this purpose.
CURRENT_PG_MAJOR=$(cat /etc/postgres-major-version 2>/dev/null || postgres --version | grep -oP '\d+' | head -1)

if [ -f "$PGDATA/PG_VERSION" ]; then
    DATA_PG_MAJOR=$(cat "$PGDATA/PG_VERSION")

    if [ "$DATA_PG_MAJOR" -gt "$CURRENT_PG_MAJOR" ]; then
        echo "ERROR: Data directory was created by PostgreSQL $DATA_PG_MAJOR but this image ships $CURRENT_PG_MAJOR."
        echo "Downgrade is not supported. Please use a newer image version."
        exit 1
    fi

    if [ "$DATA_PG_MAJOR" -lt "$CURRENT_PG_MAJOR" ]; then
        echo "PostgreSQL major-version upgrade required: $DATA_PG_MAJOR → $CURRENT_PG_MAJOR"

        OLD_BIN="/usr/lib/postgresql/${DATA_PG_MAJOR}/bin"
        NEW_BIN="/usr/lib/postgresql/${CURRENT_PG_MAJOR}/bin"
        PGDATA_NEW="/var/lib/postgresql/data_new"

        if [ ! -d "$OLD_BIN" ]; then
            echo "ERROR: Old PostgreSQL $DATA_PG_MAJOR binaries not found at $OLD_BIN."
            echo "Cannot upgrade automatically. Data is preserved at $PGDATA."
            exit 1
        fi

        # Remove any leftover working directory from a previous failed attempt
        rm -rf "$PGDATA_NEW"

        echo "Initializing new PostgreSQL $CURRENT_PG_MAJOR cluster..."
        "$NEW_BIN/initdb" -D "$PGDATA_NEW" \
            --username=windmill \
            --auth-local=trust \
            --auth-host=trust \
            --no-instructions

        echo "Running pg_upgrade (this may take a moment)..."
        # pg_upgrade writes log files to its working directory; use the volume
        # root so they persist for post-upgrade inspection if needed.
        cd /var/lib/postgresql
        if ! "$NEW_BIN/pg_upgrade" \
                --old-bindir="$OLD_BIN" \
                --new-bindir="$NEW_BIN" \
                --old-datadir="$PGDATA" \
                --new-datadir="$PGDATA_NEW"; then
            echo "ERROR: pg_upgrade failed. Old data is preserved at $PGDATA."
            echo "Check /var/lib/postgresql/pg_upgrade_output.d/ for details."
            rm -rf "$PGDATA_NEW"
            exit 1
        fi

        # Swap data directories: current → backup, upgraded → active
        mv "$PGDATA" "${PGDATA}_old_v${DATA_PG_MAJOR}"
        mv "$PGDATA_NEW" "$PGDATA"

        # Restore the custom connection settings that a fresh initdb does not set
        cat > "$PGDATA/pg_hba.conf" << 'HBAEOF'
# TYPE  DATABASE        USER            ADDRESS                 METHOD
local   all             all                                     trust
host    all             all             127.0.0.1/32            trust
host    all             all             ::1/128                 trust
HBAEOF

        echo "listen_addresses = 'localhost'" >> "$PGDATA/postgresql.conf"

        # Remove pg_upgrade artefacts from the working directory
        rm -rf /var/lib/postgresql/pg_upgrade_output.d

        echo "PostgreSQL upgrade to $CURRENT_PG_MAJOR complete."
        echo "Old data backed up at ${PGDATA}_old_v${DATA_PG_MAJOR} – safe to remove once verified."
    fi
fi
# ── End of automatic upgrade section ─────────────────────────────────────────

# Initialize PostgreSQL data directory on first run.
# No su/chown needed — we already own PGDATA (the windmill user owns the volume).
if [ -z "$(ls -A "$PGDATA" 2>/dev/null)" ]; then
    echo "Initializing PostgreSQL database for Windmill..."

    initdb -D "$PGDATA" \
        --username=windmill \
        --auth-local=trust \
        --auth-host=trust \
        --no-instructions

    # Allow local connections without a password; listen only on localhost
    cat > "$PGDATA/pg_hba.conf" << 'EOF'
# TYPE  DATABASE        USER            ADDRESS                 METHOD
local   all             all                                     trust
host    all             all             127.0.0.1/32            trust
host    all             all             ::1/128                 trust
EOF

    cat >> "$PGDATA/postgresql.conf" << 'EOF'
listen_addresses = 'localhost'
EOF

    # Start PostgreSQL temporarily to create the windmill database, then stop it.
    # supervisord will restart it properly afterward.
    pg_ctl -D "$PGDATA" start -w -o "-k /var/run/postgresql"
    psql -h /var/run/postgresql -U windmill postgres \
        -c "CREATE DATABASE windmill OWNER windmill;"
    pg_ctl -D "$PGDATA" stop -w

    echo "PostgreSQL initialization complete."
fi

exec supervisord -c /supervisord.conf
