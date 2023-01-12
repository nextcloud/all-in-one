#!/bin/bash

# Build NC_WEBROOT_P
export NC_WEBROOT_P=$([  "$NC_WEBROOT" = "/" ] && echo "" || echo "$NC_WEBROOT")

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

# Strip uri prefix, if NC_WEBROOT
if [ "$NC_WEBROOT_P" != '' ]; then
    CADDYFILE="$(sed 's|# uri_strip_webroot placeholder|uri strip_prefix {$NC_WEBROOT_P}|' /Caddyfile)"
    echo "$CADDYFILE" > /Caddyfile
fi

# Add caddy path
mkdir -p /mnt/data/caddy/

# Fix apache sturtup
rm -f /var/run/apache2/apache2.pid

exec "$@"
