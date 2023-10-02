#!/bin/bash

test -f "/mnt/data/backup-is-running" && exit 0

# Set a default value for POSTGRES_PORT
if [ -z "$POSTGRES_PORT" ]; then
    POSTGRES_PORT=5432
fi

psql -d "postgresql://oc_$POSTGRES_USER:$POSTGRES_PASSWORD@localhost:$POSTGRES_PORT/$POSTGRES_DB" -c "select now()" || exit 1
