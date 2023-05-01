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

# Change variables in case of reverse proxies
if [ "$APACHE_PORT" != '443' ]; then
    export PROTOCOL="http"
    export NC_DOMAIN=""
else
    export PROTOCOL="https"
fi

# Change the auto_https in case of reverse proxies
if [ "$APACHE_PORT" != '443' ]; then
    CADDYFILE="$(sed 's|auto_https.*|auto_https off|' /Caddyfile)"
else
    CADDYFILE="$(sed 's|auto_https.*|auto_https disable_redirects|' /Caddyfile)"
fi
echo "$CADDYFILE" > /Caddyfile

# Change the trusted_proxies in case of reverse proxies
if [ "$APACHE_PORT" != '443' ]; then
    CADDYFILE="$(sed 's|# trusted_proxies placeholder|trusted_proxies private_ranges|' /Caddyfile)"
else
    CADDYFILE="$(sed 's|trusted_proxies private_ranges|# trusted_proxies placeholder|' /Caddyfile)"
fi
echo "$CADDYFILE" > /Caddyfile

# Overwrite nextcloud conf
cat /nextcloud > /mnt/data/nextcloud

# Fix the Caddyfile format
caddy fmt --overwrite /Caddyfile

# Add caddy path
mkdir -p /mnt/data/caddy/

# Fix apache startup
rm -f /usr/local/apache2/logs/httpd.pid

exec "$@"
