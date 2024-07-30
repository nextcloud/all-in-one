#!/bin/bash

if [ -z "$NC_DOMAIN" ]; then
    echo "NC_DOMAIN and NEXTCLOUD_HOST need to be provided. Exiting!"
    exit 1
fi

# Need write access to /mnt/data
if ! [ -w /mnt/data ]; then
    echo "Cannot write to /mnt/data"
    exit 1
fi

# Only start container if nextcloud is accessible
while ! nc -z "$NEXTCLOUD_HOST" 9000; do
    echo "Waiting for Nextcloud to start..."
    sleep 5
done

bash caddyfile.sh > /tmp/Caddyfile

# Fix the Caddyfile format
caddy fmt --overwrite /tmp/Caddyfile

# Add caddy path
mkdir -p /mnt/data/caddy/

# Fix apache startup
rm -f /usr/local/apache2/logs/httpd.pid

exec "$@"
