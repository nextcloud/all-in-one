#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

test -f "/mnt/data/backup-is-running" && exit 0

# If database import is running, do not continue with the health check
if nc -z 127.0.0.1 11000; then
    exit 0
fi

PGPASSWORD="$POSTGRES_PASSWORD" psql -h 127.0.0.1 -p 5432 -U "oc_$POSTGRES_USER" -d "$POSTGRES_DB" -c "select now()" || exit 1
