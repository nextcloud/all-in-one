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
while ! nc -z "$NEXTCLOUD_HOST" 9001; do
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

# Add warning
if ! [ -f /nextcloud/custom_apps/notify_push/bin/"$CPU_ARCH"/notify_push ]; then
    echo "The notify_push binary was not found."
    echo "Most likely is DNS resolution not working correctly."
    echo "You can try to fix this by configuring a DNS server globally in dockers daemon.json."
    echo "See https://dockerlabs.collabnix.com/intermediate/networking/Configuring_DNS.html"
    echo "Afterwards a restart of docker should automatically resolve this."
    echo "Additionally, make sure to disable VPN software that might be running on your server"
    echo "Also check your firewall if it blocks connections to github"
    echo "If it should still not work afterwards, feel free to create a new thread at https://github.com/nextcloud/all-in-one/discussions/new?category=questions and post the Nextcloud container logs there."
    echo ""
    echo ""
    exit 1
fi

echo "notify-push was started"

# Set a default value for POSTGRES_PORT
if [ -z "$POSTGRES_PORT" ]; then
    POSTGRES_PORT=5432
fi
# Set sensitive values as env
export DATABASE_URL="postgres://oc_$POSTGRES_USER:$POSTGRES_PASSWORD@$POSTGRES_HOST:$POSTGRES_PORT/$POSTGRES_DB"
export REDIS_URL="redis://:$REDIS_HOST_PASSWORD@$REDIS_HOST"

# Run it
/nextcloud/custom_apps/notify_push/bin/"$CPU_ARCH"/notify_push \
    --database-prefix="oc_" \
    --nextcloud-url "https://$NC_DOMAIN" \
    --port 7867

exec "$@"
