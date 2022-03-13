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

if [ -z "$APACHE_PORT" ]; then
    export APACHE_PORT="443"
fi

if [ "$APACHE_PORT" != '443' ]; then
    export PROTOCOL="http"
    export NC_DOMAIN=""
    sed -i 's|auto_https.*|auto_https off|' /Caddyfile
else
    export PROTOCOL="https"
    sed -i 's|auto_https.*|auto_https disable_redirects|' /Caddyfile
fi

# Add caddy path
mkdir -p /mnt/data/caddy/

# Fix apache sturtup
rm -f /var/run/apache2/apache2.pid

exec "$@"