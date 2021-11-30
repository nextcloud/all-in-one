#!/bin/bash

# Only start container if database is accessible
while ! nc -z "$POSTGRES_HOST" 5432; do
    echo "Waiting for database to start..."
    sleep 5
done

# Run original entrypoint
if ! bash /entrypoint.sh; then
    exit 1
fi

exec "$@"