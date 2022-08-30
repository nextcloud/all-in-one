#!/bin/bash

# Only start container if database is accessible
while ! nc -z "$POSTGRES_HOST" 5432; do
    echo "Waiting for database to start..."
    sleep 5
done

# Use the correct Postgres username
POSTGRES_USER="oc_$POSTGRES_USER"
export POSTGRES_USER

# Fix false database connection on old instances
if [ -f "/var/www/html/config/config.php" ]; then
    sleep 2
    while ! psql -d "postgresql://$POSTGRES_USER:$POSTGRES_PASSWORD@$POSTGRES_HOST:5432/$POSTGRES_DB" -c "select now()"; do
        echo "Waiting for the database to start..."
        sleep 5
    done
    # The code below is hopefully not needed anymore. Was introduced with https://github.com/nextcloud/all-in-one/pull/218
    # sed -i "s|'dbuser'.*=>.*$|'dbuser' => '$POSTGRES_USER',|" /var/www/html/config/config.php
    # sed -i "s|'dbpassword'.*=>.*$|'dbpassword' => '$POSTGRES_PASSWORD',|" /var/www/html/config/config.php
fi

# Run original entrypoint
if ! bash /entrypoint.sh; then
    exit 1
fi

# Correctly set CPU_ARCH for notify_push
CPU_ARCH="$(uname -m)"
export CPU_ARCH
if [ -z "$CPU_ARCH" ]; then
    echo "Could not get processor architecture. Exiting."
    exit 1
elif [ "$CPU_ARCH" != "x86_64" ]; then
    export CPU_ARCH="aarch64"
fi

exec "$@"