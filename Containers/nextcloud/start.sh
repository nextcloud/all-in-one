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

# Correctly set CPU_ARCH for notify_push
export CPU_ARCH="$(uname -m)"
if [ -z "$CPU_ARCH" ]; then
    echo "Could not get processor architecture. Exiting."
    exit 1
elif [ "$CPU_ARCH" != "x86_64" ]; then
    export CPU_ARCH="aarch64"
fi

exec "$@"