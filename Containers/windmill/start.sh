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
DUMP_FILE="$PGDATA/windmill-db-dump.sql"

# Staging directory for the new cluster during a major-version upgrade.
# It lives INSIDE $PGDATA (the persistent volume) so the dump file is never
# on tmpfs and is never lost if the container crashes mid-upgrade.
UPGRADE_DIR="$PGDATA/upgrade_data"

# Current PG major version as shipped in this image
CURRENT_PG_MAJOR=$(cat /etc/postgres-major-version 2>/dev/null)

# ── Don't start if previous import failed ────────────────────────────────────
if [ -f "$PGDATA/import.failed" ]; then
    echo "The database import failed the last time. Please restore a backup and try again."
    echo "For further clues on what went wrong, look at the logs above."
    if [ -d "$UPGRADE_DIR" ]; then
        echo "The staged upgraded cluster is still present at $UPGRADE_DIR."
    fi
    exit 1
fi

# ── Don't start if previous export failed ────────────────────────────────────
if [ -f "$PGDATA/export.failed" ]; then
    echo "Database export failed the last time. Most likely was the export time not high enough."
    echo "Please report this to https://github.com/nextcloud/all-in-one/issues. Thanks!"
    exit 1
fi

# ── Clean up any leftover upgrade staging directory ──────────────────────────
# Normally absent; only present if a previous upgrade was interrupted after the
# swap completed but before the directory was removed (extremely unlikely).
if [ -d "$UPGRADE_DIR" ]; then
    echo "Removing leftover upgrade staging directory..."
    rm -rf "$UPGRADE_DIR"
fi

# Write the standard pg_hba.conf and listen_addresses settings into a data directory.
configure_pg() {
    local datadir="$1"
    cat > "$datadir/pg_hba.conf" << 'HBAEOF'
# TYPE  DATABASE        USER            ADDRESS                 METHOD
local   all             all                                     trust
host    all             all             127.0.0.1/32            trust
host    all             all             ::1/128                 trust
HBAEOF
    echo "listen_addresses = 'localhost'" >> "$datadir/postgresql.conf"
}

# ── PostgreSQL major-version upgrade via dump/restore ────────────────────────
if [ -f "$PGDATA/PG_VERSION" ]; then
    DATA_PG_MAJOR=$(cat "$PGDATA/PG_VERSION")

    if [ "$DATA_PG_MAJOR" -gt "$CURRENT_PG_MAJOR" ]; then
        echo "ERROR: Data directory was created by PostgreSQL $DATA_PG_MAJOR but this image ships $CURRENT_PG_MAJOR."
        echo "Downgrade is not supported. Please use a newer image version."
        exit 1
    fi

    if [ "$DATA_PG_MAJOR" -lt "$CURRENT_PG_MAJOR" ]; then
        echo "PostgreSQL major-version upgrade required: $DATA_PG_MAJOR → $CURRENT_PG_MAJOR"

        if ! [ -f "$DUMP_FILE" ]; then
            echo "Unable to upgrade because the database dump is missing."
            echo "Please restore a backup and try again."
            exit 1
        fi

        # Write output to logfile so the import can be inspected later
        exec > >(tee -i "$PGDATA/database-import.log")
        exec 2>&1

        echo "Restoring database from dump into new PostgreSQL $CURRENT_PG_MAJOR cluster."

        # Set the sentinel BEFORE any destructive operation so that a crash at
        # any point leaves the guard in place and blocks the next start.
        touch "$PGDATA/import.failed"

        set -ex

        # Initialise the new cluster in a subdirectory of the persistent volume.
        # This keeps the dump file ($DUMP_FILE) untouched throughout the upgrade;
        # no data is ever copied to or relied upon from tmpfs.
        rm -rf "$UPGRADE_DIR"
        mkdir "$UPGRADE_DIR"

        initdb -D "$UPGRADE_DIR" \
            --username=windmill \
            --auth-local=trust \
            --auth-host=trust \
            --no-instructions

        configure_pg "$UPGRADE_DIR"

        # Start postgres temporarily on an alternate TCP port so we can import.
        # Use explicit flags; do NOT export PGPORT to avoid side-effects.
        postgres -D "$UPGRADE_DIR" -h 127.0.0.1 -p 11000 &
        TEMP_PG_PID=$!

        # Wait until postgres accepts connections
        while ! psql -h 127.0.0.1 -p 11000 -U windmill -d postgres -c "select now()" > /dev/null 2>&1; do
            echo "Waiting for the temporary database to start..."
            sleep 5
        done

        # Create the windmill database
        psql -h 127.0.0.1 -p 11000 -U windmill -d postgres \
            -c "CREATE DATABASE windmill OWNER windmill;"

        # Restore from dump.  $DUMP_FILE still lives in $PGDATA — it was never
        # wiped because we used $UPGRADE_DIR for the new cluster.
        echo "Restoring the database from dump..."
        psql -h 127.0.0.1 -p 11000 -U windmill -d windmill < "$DUMP_FILE"

        # Stop the temporary postgres cleanly
        pg_ctl -D "$UPGRADE_DIR" stop -m smart -t 1800
        wait "$TEMP_PG_PID" 2>/dev/null || true

        # ── Swap the upgraded cluster into the main PGDATA slot ──────────────
        # Remove all old cluster files except: the staging dir, the dump,
        # the import log, and the import.failed sentinel.
        DUMP_BASENAME="$(basename "$DUMP_FILE")"
        find "$PGDATA" -maxdepth 1 -mindepth 1 \
            ! -name 'upgrade_data' \
            ! -name "$DUMP_BASENAME" \
            ! -name 'database-import.log' \
            ! -name 'import.failed' \
            -exec rm -rf {} +

        # Move the new cluster files into PGDATA
        find "$UPGRADE_DIR" -maxdepth 1 -mindepth 1 -exec mv -t "$PGDATA" {} +

        # Remove the now-empty staging directory
        rmdir "$UPGRADE_DIR"

        set +ex

        # Remove the sentinel only after the swap has fully completed
        rm "$PGDATA/import.failed"
        echo "PostgreSQL upgrade to $CURRENT_PG_MAJOR complete."
    fi
fi
# ── End of major-version upgrade section ─────────────────────────────────────

# ── Initialize PostgreSQL data directory on first run ────────────────────────
if [ -z "$(ls -A "$PGDATA" 2>/dev/null)" ]; then
    echo "Initializing PostgreSQL database for Windmill..."

    initdb -D "$PGDATA" \
        --username=windmill \
        --auth-local=trust \
        --auth-host=trust \
        --no-instructions

    configure_pg "$PGDATA"

    # Start PostgreSQL temporarily to create the windmill database, then stop it.
    # supervisord will restart it properly afterward.
    pg_ctl -D "$PGDATA" start -w -o "-k /var/run/postgresql"
    psql -h /var/run/postgresql -U windmill postgres \
        -c "CREATE DATABASE windmill OWNER windmill;"
    pg_ctl -D "$PGDATA" stop -w

    echo "PostgreSQL initialization complete."
fi

# ── Dump database and shut down on container stop ────────────────────────────
do_database_dump() {
    # Stop windmill first so it is not writing to the database during the dump.
    supervisorctl -c /supervisord.conf stop windmill 2>/dev/null || true

    # Verify postgres is still accepting connections before attempting the dump.
    if ! pg_isready -h /var/run/postgresql -U windmill -q; then
        echo "WARNING: postgres is not ready; skipping dump."
        kill "$SUPERVISORD_PID" 2>/dev/null || true
        wait "$SUPERVISORD_PID" 2>/dev/null || true
        return
    fi

    set -x
    touch "$PGDATA/export.failed"
    rm -f "$DUMP_FILE.temp"
    if pg_dump -h /var/run/postgresql -U windmill windmill > "$DUMP_FILE.temp"; then
        mv "$DUMP_FILE.temp" "$DUMP_FILE"
        rm "$PGDATA/export.failed"
        echo "Database dump successful!"
    else
        rm -f "$DUMP_FILE.temp"
        echo "Database dump unsuccessful!"
    fi
    set +x

    # Stop supervisord (which stops postgres and any remaining programs)
    kill "$SUPERVISORD_PID" 2>/dev/null || true
    wait "$SUPERVISORD_PID" 2>/dev/null || true
}

trap do_database_dump SIGTERM SIGINT

# ── Start services ────────────────────────────────────────────────────────────
supervisord -c /supervisord.conf &
SUPERVISORD_PID=$!
wait "$SUPERVISORD_PID"
