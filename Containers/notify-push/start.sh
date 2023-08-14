#!/bin/bash

if [ -z "$NEXTCLOUD_HOST" ]; then
    echo "NEXTCLOUD_HOST need to be provided. Exiting!"
    exit 1
elif [ -z "$POSTGRES_HOST" ]; then
    echo "POSTGRES_HOST need to be provided. Exiting!"
    exit 1
elif [ -z "$REDIS_HOST" ]; then
    echo "REDIS_HOST need to be provided. Exiting!"
    exit 1
fi

# Only start container if nextcloud is accessible
while ! nc -z "$NEXTCLOUD_HOST" 9000; do
    echo "Waiting for Nextcloud to start..."
    sleep 5
done

# Correctly set CPU_ARCH for notify_push
CPU_ARCH="$(uname -m)"
export CPU_ARCH
if [ -z "$CPU_ARCH" ]; then
    echo "Could not get processor architecture. Exiting."
    exit 1
elif [ "$CPU_ARCH" != "x86_64" ]; then
    export CPU_ARCH="aarch64"
fi

# Set sensitive values as env
export DATABASE_URL="postgres://$POSTGRES_USER:$POSTGRES_PASSWORD@$POSTGRES_HOST/$POSTGRES_DB"
export REDIS_URL="redis://:$REDIS_HOST_PASSWORD@$REDIS_HOST"

echo "Starting notify-push. If not further logs are shown below, it started correctly."

# Run it
/nextcloud/custom_apps/notify_push/bin/"$CPU_ARCH"/notify_push \
    --database-prefix="oc_" \
    --nextcloud-url "https://$NC_DOMAIN" \
    --port 7867

exec "$@"
