#!/bin/bash

# Set a default value for POSTGRES_PORT
if [ -z "$POSTGRES_PORT" ]; then
    POSTGRES_PORT=5432
fi

nc -z "$POSTGRES_HOST" "$POSTGRES_PORT" || exit 0

if ! nc -z localhost 9000; then
    exit 1
fi
