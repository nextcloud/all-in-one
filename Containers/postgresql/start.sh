#!/bin/bash

# Variables
DATADIR="/var/lib/postgresql/data"
export DUMP_DIR="/mnt/data"
DUMP_FILE="$DUMP_DIR/database-dump.sql"
export PGPASSWORD="$POSTGRES_PASSWORD"

# Don't start database as long as backup is running
while [ -f "$DUMP_DIR/backup-is-running" ]; do
    echo "Waiting for backup container to finish..."
    echo "If this is incorrect because the backup container is not running anymore (because it was forcefully killed), you might delete the lock file:"
    echo "sudo docker exec --user root nextcloud-aio-database rm /mnt/data/backup-is-running"
    sleep 10
done

# Check if dump dir is writeable
if ! [ -w "$DUMP_DIR" ]; then
    echo "DUMP dir is not writeable by postgres user."
    exit 1
fi

# Don't start if import failed
if [ -f "$DUMP_DIR/import.failed" ]; then
    echo "The database import failed. Please restore a backup and try again."
    echo "For further clues on what went wrong, look at the logs above."
    exit 1
fi

# Don't start if initialization failed
if [ -f "$DUMP_DIR/initialization.failed" ]; then
    echo "The database initialization failed. Most likely was a wrong timezone selected."
    echo "The selected timezone is '$TZ'." 
    echo "Please check if it is in the 'TZ identifier' column of the timezone list: https://en.wikipedia.org/wiki/List_of_tz_database_time_zones#List"
    echo "For further clues on what went wrong, look at the logs above."
    echo "You might start again from scratch by following https://github.com/nextcloud/all-in-one#how-to-properly-reset-the-instance and selecting a proper timezone."
    exit 1
fi

# Delete the datadir once (needed for setting the correct credentials on old instances once)
if ! [ -f "$DUMP_DIR/export.failed" ] && ! [ -f "$DUMP_DIR/initial-cleanup-done" ]; then
    set -ex
    rm -rf "${DATADIR:?}/"*
    touch "$DUMP_DIR/initial-cleanup-done"
    set +ex
fi

# Test if some things match
# shellcheck disable=SC2235
if ( [ -f "$DATADIR/PG_VERSION" ] && [ "$PG_MAJOR" != "$(cat "$DATADIR/PG_VERSION")" ] ) \
|| ( ! [ -f "$DATADIR/PG_VERSION" ] && ( [ -f "$DUMP_FILE" ] || [ -f "$DUMP_DIR/export.failed" ] ) ); then
    # The DUMP_file must be provided
    if ! [ -f "$DUMP_FILE" ]; then
        echo "Unable to restore the database because the database dump is missing."
        exit 1
    fi

    # If database export was unsuccessful, skip update 
    if [ -f "$DUMP_DIR/export.failed" ]; then
        echo "Database export failed the last time. Most likely was the export time not high enough."
        echo "Please report this to https://github.com/nextcloud/all-in-one/issues. Thanks!"
        exit 1
    fi

    # Write output to logfile.
    exec > >(tee -i "$DUMP_DIR/database-import.log")
    exec 2>&1

    # Inform
    echo "Restoring from database dump."

    # Add import.failed file
    touch "$DUMP_DIR/import.failed"

    # Exit if any command fails
    set -ex

    # Remove old database files
    rm -rf "${DATADIR:?}/"*

    # Change database port to a random port temporarily
    export PGPORT=11000

    # Create new database
    exec docker-entrypoint.sh postgres &

    # Wait for creation
    while ! psql -d "postgresql://oc_$POSTGRES_USER:$POSTGRES_PASSWORD@127.0.0.1:11000/$POSTGRES_DB" -c "select now()"; do
        echo "Waiting for the database to start."
        sleep 5
    done

    # Check if the line we grep for later on is there
    GREP_STRING='Name: oc_appconfig; Type: TABLE; Schema: public; Owner:'
    if ! grep -qa "$GREP_STRING" "$DUMP_FILE"; then
        echo "The needed oc_appconfig line is not there which is unexpected."
        echo "Please report this to https://github.com/nextcloud/all-in-one/issues. Thanks!"
        exit 1
    fi

    # Get the Owner
    DB_OWNER="$(grep -a "$GREP_STRING" "$DUMP_FILE" | head -1 | grep -oP 'Owner:.*$' | sed 's|Owner:||;s|[[:space:]]||g')"
    if [ "$DB_OWNER" = "$POSTGRES_USER" ]; then
        echo "Unfortunately was the found database owner of the dump file the same as the POSTGRES_USER $POSTGRES_USER"
        echo "It is not possible to import a database dump from this database owner."
        echo "However you might rename the owner in the dumpfile to something else."
        exit 1
    elif [ "$DB_OWNER" != "oc_$POSTGRES_USER" ]; then
        DIFFERENT_DB_OWNER=1
        psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
            CREATE USER "$DB_OWNER" WITH PASSWORD '$POSTGRES_PASSWORD' CREATEDB;
            ALTER DATABASE "$POSTGRES_DB" OWNER TO "$DB_OWNER";
            GRANT ALL PRIVILEGES ON DATABASE "$POSTGRES_DB" TO "$DB_OWNER";
            GRANT ALL PRIVILEGES ON SCHEMA public TO "$DB_OWNER";
EOSQL
    fi

    # Restore database
    echo "Restoring the database from database dump"
    psql "$POSTGRES_DB" -U "$POSTGRES_USER" < "$DUMP_FILE"

    # Correct permissions
    if [ -n "$DIFFERENT_DB_OWNER" ]; then
        psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
            ALTER DATABASE "$POSTGRES_DB" OWNER TO "oc_$POSTGRES_USER";
            REASSIGN OWNED BY "$DB_OWNER" TO "oc_$POSTGRES_USER";
EOSQL
    fi

    # Shut down the database to be able to start it again
    # The smart mode disallows new connections, then waits for all existing clients to disconnect and any online backup to finish
    # Wait for 1800s to make sure that a checkpoint is completed successfully
    pg_ctl stop -m smart -t 1800

    # Change database port back to default
    export PGPORT=5432

    # Don't exit if command fails anymore
    set +ex

    # Remove import failed file if everything went correctly
    rm "$DUMP_DIR/import.failed"
fi

# Cover the last case
if ! [ -f "$DATADIR/PG_VERSION" ] && ! [ -f "$DUMP_FILE" ]; then
    # Remove old database files if somehow there should be some
    rm -rf "${DATADIR:?}/"*
fi

# Modify postgresql.conf
if [ -f "/var/lib/postgresql/data/postgresql.conf" ]; then
    echo "Setting postgres values..."
    PGCONF="/var/lib/postgresql/data/postgresql.conf"

    # Sync this with max pm.max_children and MaxRequestWorkers
    # 5000 connections is apparently the highest possible value with postgres so set it to that so that we don't run into a limit here.
    # We don't actually expect so many connections but don't want to limit it artificially because people will report issues otherwise
    # Also connections should usually be closed again after the process is done
    # If we should actually exceed this limit, it is definitely a bug in Nextcloud server or some of its apps that does not close connections correctly and not a bug in AIO
    sed -i "s|^max_connections =.*|max_connections = 5000|" "$PGCONF"

    # Do not log checkpoints
    if grep -q "#log_checkpoints" "$PGCONF"; then
        sed -i 's|#log_checkpoints.*|log_checkpoints = off|' "$PGCONF"
    fi

    # Closing idling connections automatically seems to break any logic so was reverted again to default where it is disabled
    if grep -q "^idle_session_timeout" "$PGCONF"; then
        sed -i 's|^idle_session_timeout.*|#idle_session_timeout|' "$PGCONF"
    fi

    # Increase shared_buffers from the 128MB default for better data caching
    sed -i "s|^#shared_buffers = .*|shared_buffers = 256MB|" "$PGCONF"
    sed -i "s|^shared_buffers = .*|shared_buffers = 256MB|" "$PGCONF"

    # Hint to the query planner about available OS page cache (does not allocate memory)
    sed -i "s|^#effective_cache_size = .*|effective_cache_size = 1GB|" "$PGCONF"
    sed -i "s|^effective_cache_size = .*|effective_cache_size = 1GB|" "$PGCONF"

    # Increase per-operation sort/hash memory to reduce disk spills for file listing and share queries.
    # Note: this is allocated per sort/hash operation, not per connection, so the theoretical worst-case
    # (max_connections × work_mem) is rarely approached in practice.
    sed -i "s|^#work_mem = .*|work_mem = 16MB|" "$PGCONF"
    sed -i "s|^work_mem = .*|work_mem = 16MB|" "$PGCONF"

    # Increase memory for VACUUM, CREATE INDEX, and other maintenance operations
    sed -i "s|^#maintenance_work_mem = .*|maintenance_work_mem = 256MB|" "$PGCONF"
    sed -i "s|^maintenance_work_mem = .*|maintenance_work_mem = 256MB|" "$PGCONF"

    # Increase WAL buffers to reduce WAL write latency under concurrent write load
    sed -i "s|^#wal_buffers = .*|wal_buffers = 16MB|" "$PGCONF"
    sed -i "s|^wal_buffers = .*|wal_buffers = 16MB|" "$PGCONF"

    # Spread checkpoint I/O over a longer window to reduce spikes
    sed -i "s|^#checkpoint_timeout = .*|checkpoint_timeout = 15min|" "$PGCONF"
    sed -i "s|^checkpoint_timeout = .*|checkpoint_timeout = 15min|" "$PGCONF"

    # Tune for SSD storage: random reads are nearly as fast as sequential reads
    sed -i "s|^#random_page_cost = .*|random_page_cost = 1.1|" "$PGCONF"
    sed -i "s|^random_page_cost = .*|random_page_cost = 1.1|" "$PGCONF"

    # Allow the kernel to issue more concurrent I/O prefetch requests (suitable for SSDs)
    sed -i "s|^#effective_io_concurrency = .*|effective_io_concurrency = 200|" "$PGCONF"
    sed -i "s|^effective_io_concurrency = .*|effective_io_concurrency = 200|" "$PGCONF"

    # Trigger autovacuum earlier on large Nextcloud tables (e.g. oc_filecache, oc_activity)
    # to prevent table bloat accumulating before the default 20% threshold is reached
    sed -i "s|^#autovacuum_vacuum_scale_factor = .*|autovacuum_vacuum_scale_factor = 0.05|" "$PGCONF"
    sed -i "s|^autovacuum_vacuum_scale_factor = .*|autovacuum_vacuum_scale_factor = 0.05|" "$PGCONF"
    sed -i "s|^#autovacuum_analyze_scale_factor = .*|autovacuum_analyze_scale_factor = 0.02|" "$PGCONF"
    sed -i "s|^autovacuum_analyze_scale_factor = .*|autovacuum_analyze_scale_factor = 0.02|" "$PGCONF"
fi

do_database_dump() {
    set -x
    rm -f "$DUMP_FILE.temp"
    touch "$DUMP_DIR/export.failed"
    if pg_dump --username "$POSTGRES_USER" "$POSTGRES_DB" > "$DUMP_FILE.temp"; then
        rm -f "$DUMP_FILE"
        mv "$DUMP_FILE.temp" "$DUMP_FILE"
        pg_ctl stop -m fast
        rm "$DUMP_DIR/export.failed"
        echo 'Database dump successful!'
        set +x
        exit 0
    else
        pg_ctl stop -m fast
        echo "Database dump unsuccessful!"
        set +x
        exit 1
    fi
}

# Catch docker stop attempts
trap do_database_dump SIGINT SIGTERM

# Start the database
exec docker-entrypoint.sh postgres &
wait $!
