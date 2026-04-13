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

# Apply log level to Caddy and Apache httpd
case "${AIO_LOG_LEVEL:-warning}" in
    debug)   CADDY_LOG_LEVEL="DEBUG"; APACHE_LOG_LEVEL="debug" ;;
    info)    CADDY_LOG_LEVEL="INFO";  APACHE_LOG_LEVEL="info" ;;
    warning) CADDY_LOG_LEVEL="WARN";  APACHE_LOG_LEVEL="warn" ;;
    error)   CADDY_LOG_LEVEL="ERROR"; APACHE_LOG_LEVEL="error" ;;
    *)       CADDY_LOG_LEVEL="WARN";  APACHE_LOG_LEVEL="warn" ;;
esac

# Change the auto_https in case of reverse proxies
if [ "$APACHE_PORT" != '443' ]; then
    CADDYFILE="$(sed 's|auto_https.*|auto_https off|' /Caddyfile)"
else
    CADDYFILE="$(sed 's|auto_https.*|auto_https disable_redirects|' /Caddyfile)"
fi
echo "$CADDYFILE" > /tmp/Caddyfile

# Apply Caddy log level
CADDYFILE="$(sed "s|level [A-Z]*|level $CADDY_LOG_LEVEL|" /tmp/Caddyfile)"
echo "$CADDYFILE" > /tmp/Caddyfile

# Change the trusted_proxies in case of reverse proxies
if [ "$APACHE_PORT" != '443' ]; then
    # Here the 100.64.0.0/10 range gets added which is the CGNAT range used by Tailscale nodes
    # See https://github.com/nextcloud/all-in-one/pull/6703 for reference
    CADDYFILE="$(sed 's|# trusted_proxies placeholder|trusted_proxies static private_ranges 100.64.0.0/10|' /tmp/Caddyfile)"
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

# Fix caddy startup
if [ -d "/mnt/data/caddy/locks" ]; then
    rm -rf /mnt/data/caddy/locks/*
fi

# Fix apache startup
rm -f /usr/local/apache2/logs/httpd.pid

# Apply Apache httpd log level
sed -i "s|LogLevel [a-z]*|LogLevel $APACHE_LOG_LEVEL|" /usr/local/apache2/conf/nextcloud.conf

# Apply supervisord log level (supervisord.conf is not writable by this user, so use /tmp copy)
case "${AIO_LOG_LEVEL:-warning}" in
    debug)   SUPERVISORD_LOG_LEVEL="debug" ;;
    info)    SUPERVISORD_LOG_LEVEL="info" ;;
    warning) SUPERVISORD_LOG_LEVEL="warn" ;;
    error)   SUPERVISORD_LOG_LEVEL="error" ;;
    *)       SUPERVISORD_LOG_LEVEL="warn" ;;
esac
cp /supervisord.conf /tmp/supervisord.conf
sed -i "s|loglevel=.*|loglevel=$SUPERVISORD_LOG_LEVEL|" /tmp/supervisord.conf

exec /usr/bin/supervisord -c /tmp/supervisord.conf
