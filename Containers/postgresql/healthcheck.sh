#!/bin/bash

test -f "/mnt/data/backup-is-running" && exit 0

psql -d "postgresql://oc_$POSTGRES_USER:$POSTGRES_PASSWORD@127.0.0.1:11000/$POSTGRES_DB" -c "select now()" && exit 0

psql -d "postgresql://oc_$POSTGRES_USER:$POSTGRES_PASSWORD@127.0.0.1:5432/$POSTGRES_DB" -c "select now()" || exit 1
