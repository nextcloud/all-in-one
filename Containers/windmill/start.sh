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

# The dump and its sentinel/log files live on a dedicated Docker volume that is
# separate from the postgres data directory.  This means the dump survives a
# complete PGDATA wipe (which happens during a major-version upgrade) and there
# is no need for a staging subdirectory or complex file exclusion logic.
DUMP_DIR="/var/lib/windmill-dump"
DUMP_FILE="$DUMP_DIR/windmill-db-dump.sql"

# Current PG major version as shipped in this image
CURRENT_PG_MAJOR=$(cat /etc/postgres-major-version 2>/dev/null)

# ── Don't start if previous import failed ────────────────────────────────────
if [ -f "$DUMP_DIR/import.failed" ]; then
    echo "The database import failed the last time. Please restore a backup and try again."
    echo "For further clues on what went wrong, look at the logs above."
    exit 1
fi

# ── Don't start if previous export failed ────────────────────────────────────
if [ -f "$DUMP_DIR/export.failed" ]; then
    echo "Database export failed the last time. Most likely was the export time not high enough."
    echo "Please report this to https://github.com/nextcloud/all-in-one/issues. Thanks!"
    exit 1
fi

# Write the standard pg_hba.conf and listen_addresses settings into a data directory.
configure_pg() {
    local datadir="$1"
    cat > "$datadir/pg_hba.conf" << 'HBAEOF'
# TYPE  DATABASE        USER            ADDRESS                 METHOD
local   all             all                                     trust
HBAEOF
    # Disable TCP entirely; all communication uses the Unix socket.
    echo "listen_addresses = ''" >> "$datadir/postgresql.conf"
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
        exec > >(tee -i "$DUMP_DIR/database-import.log")
        exec 2>&1

        echo "Restoring database from dump into new PostgreSQL $CURRENT_PG_MAJOR cluster."

        # Set the sentinel BEFORE any destructive operation so that a crash at
        # any point leaves the guard in place and blocks the next start.
        # The sentinel lives on the dump volume and therefore survives the PGDATA wipe.
        touch "$DUMP_DIR/import.failed"

        set -ex

        # Wipe the old cluster and initialise a fresh one.
        # The dump file is on a separate volume and is not affected.
        rm -rf "${PGDATA:?}/"*

        initdb -D "$PGDATA" \
            --username=windmill \
            --auth-local=trust \
            --auth-host=trust \
            --no-instructions

        configure_pg "$PGDATA"

        # Start postgres temporarily on a socket in /tmp so we can import.
        # No TCP port is needed since we connect via the socket.
        postgres -D "$PGDATA" -k /tmp -h "" &
        TEMP_PG_PID=$!

        # Wait until postgres accepts connections
        while ! psql -h /tmp -U windmill -d postgres -c "select now()" > /dev/null 2>&1; do
            echo "Waiting for the temporary database to start..."
            sleep 5
        done

        # Create the windmill database
        psql -h /tmp -U windmill -d postgres \
            -c "CREATE DATABASE windmill OWNER windmill;"

        # Restore from dump
        echo "Restoring the database from dump..."
        psql -h /tmp -U windmill -d windmill < "$DUMP_FILE"

        # Stop the temporary postgres cleanly
        pg_ctl -D "$PGDATA" stop -m smart -t 1800
        wait "$TEMP_PG_PID" 2>/dev/null || true

        set +ex

        # Remove the sentinel only after the restore has fully completed
        rm "$DUMP_DIR/import.failed"
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
    # pg_dump uses a consistent transaction snapshot, so it is safe to run
    # while Windmill is still connected — no need to stop it beforehand.

    # Verify postgres is still accepting connections before attempting the dump.
    if ! pg_isready -h /var/run/postgresql -U windmill -q; then
        echo "WARNING: postgres is not ready; skipping dump."
        kill "$SUPERVISORD_PID" 2>/dev/null || true
        wait "$SUPERVISORD_PID" 2>/dev/null || true
        return
    fi

    set -x
    touch "$DUMP_DIR/export.failed"
    rm -f "$DUMP_FILE.temp"
    if pg_dump -h /var/run/postgresql -U windmill windmill > "$DUMP_FILE.temp"; then
        mv "$DUMP_FILE.temp" "$DUMP_FILE"
        rm "$DUMP_DIR/export.failed"
        echo "Database dump successful!"
    else
        rm -f "$DUMP_FILE.temp"
        echo "Database dump unsuccessful!"
    fi
    set +x

    # Stop supervisord (which stops postgres and windmill)
    kill "$SUPERVISORD_PID" 2>/dev/null || true
    wait "$SUPERVISORD_PID" 2>/dev/null || true
}

trap do_database_dump SIGTERM SIGINT

# ── Start services ────────────────────────────────────────────────────────────
supervisord -c /supervisord.conf &
SUPERVISORD_PID=$!
wait "$SUPERVISORD_PID"
