#!/bin/bash

# Variables
DATADIR="/var/lib/postgresql/data"
DUMP_DIR="/mnt/data"
DUMP_FILE="$DUMP_DIR/database-dump.sql"
export PGPASSWORD="$POSTGRES_PASSWORD"

# Don't start database as long as backup is running
while [ -f "$DUMP_DIR/backup-is-running" ]; do
    echo "Waiting for backup container to finish..."
    sleep 10
done

# Check if dump dir is writeable
if ! [ -w "$DUMP_DIR" ]; then
    echo "DUMP dir is not writeable by postgres user."
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

    # Inform
    echo "Restoring from database dump."

    # Exit if any command fails
    set -ex

    # Remove old database files
    rm -rf "${DATADIR:?}/"*

    # Change database port to a random port temporarily
    export PGPORT=11000

    # Create new database
    exec docker-entrypoint.sh postgres &

    # Wait for creation
    while ! nc -z localhost 11000; do
        echo "Waiting for the database to start."
        sleep 5
    done

    # Check if the line we grep for later on is there
    GREP_STRING='Name: oc_appconfig; Type: TABLE; Schema: public; Owner:'
    if ! grep -q "$GREP_STRING" "$DUMP_FILE"; then
        echo "The needed oc_appconfig line is not there which is unexpected."
        echo "Please report this to https://github.com/nextcloud/all-in-one/issues. Thanks!"
        exit 1
    fi

    # Get the Owner
    DB_OWNER="$(grep "$GREP_STRING" "$DUMP_FILE" | grep -oP 'Owner:.*$' | sed 's|Owner:||;s| ||g')"
    if [ "$DB_OWNER" != "oc_$POSTGRES_USER" ]; then
        DIFFERENT_DB_OWNER=1
        psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
            CREATE USER "$DB_OWNER" WITH PASSWORD '$POSTGRES_PASSWORD' CREATEDB;
            ALTER DATABASE "$POSTGRES_DB" OWNER TO "$DB_OWNER";
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
    pg_ctl stop -m fast

    # Change database port back to default
    export PGPORT=5432

    # Don't exit if command fails anymore
    set +ex
fi

# Cover the last case
if ! [ -f "$DATADIR/PG_VERSION" ] && ! [ -f "$DUMP_FILE" ]; then
    # Remove old database files if somehow there should be some
    rm -rf "${DATADIR:?}/"*
fi

echo "Setting max connections..."
MEMORY=$(mawk '/MemTotal/ {printf "%d", $2/1024}' /proc/meminfo)
MAX_CONNECTIONS=$((MEMORY/50+3))
if [ -n "$MAX_CONNECTIONS" ]; then
    sed -i "s|^max_connections =.*|max_connections = $MAX_CONNECTIONS|" "/var/lib/postgresql/data/postgresql.conf"
fi

# Catch docker stop attempts
trap 'true' SIGINT SIGTERM

# Start the database
exec docker-entrypoint.sh postgres &
wait $!

# Continue with shutdown procedure: do database dump, etc.
rm -f "$DUMP_FILE.temp"
touch "$DUMP_DIR/export.failed"
if pg_dump --username "$POSTGRES_USER" "$POSTGRES_DB" > "$DUMP_FILE.temp"; then
    rm -f "$DUMP_FILE"
    mv "$DUMP_FILE.temp" "$DUMP_FILE"
    pg_ctl stop -m fast
    rm "$DUMP_DIR/export.failed"
    echo 'Database dump successful!'
    exit 0
else
    pg_ctl stop -m fast
    echo "Database dump unsuccessful!"
    exit 1
fi
