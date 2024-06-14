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

# Get ipv4-address of Apache
# shellcheck disable=SC2153
IPv4_ADDRESS="$(dig "$APACHE_HOST" A +short +search | head -1)"
# Bring it in CIDR notation
# shellcheck disable=SC2001
IPv4_ADDRESS="$(echo "$IPv4_ADDRESS" | sed 's|[0-9]\+$|0/16|')"

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
echo "$CADDYFILE" > /tmp/Caddyfile

# Change the trusted_proxies in case of reverse proxies
if [ "$APACHE_PORT" != '443' ]; then
    CADDYFILE="$(sed 's|# trusted_proxies placeholder|trusted_proxies static private_ranges|' /tmp/Caddyfile)"
else
    CADDYFILE="$(sed "s|# trusted_proxies placeholder|trusted_proxies static $IPv4_ADDRESS|" /tmp/Caddyfile)"
fi
echo "$CADDYFILE" > /tmp/Caddyfile

# Remove additional domain if not given
if [ -z "$ADDITIONAL_TRUSTED_DOMAIN" ]; then
    CADDYFILE="$(sed '/ADDITIONAL_TRUSTED_DOMAIN/d' /tmp/Caddyfile)"
fi
echo "$CADDYFILE" > /tmp/Caddyfile

# Fix the Caddyfile format
caddy fmt --overwrite /tmp/Caddyfile

# Add caddy path
mkdir -p /mnt/data/caddy/

# Fix apache startup
rm -f /usr/local/apache2/logs/httpd.pid

exec "$@"
