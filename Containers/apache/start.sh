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

if [ -z "$CADDY_ROUTES" ]; then
    export CADDY_ROUTES="|/browser,0,nextcloud-aio-collabora,9980;/hosting,0,nextcloud-aio-collabora,9980;/cool,0,nextcloud-aio-collabora,9980;/push,1,nextcloud-aio-notify-push,7867;/standalone-signaling,1,nextcloud-aio-talk,8081"
fi

if [ -z "$APACHE_PORT" ]; then
    export APACHE_PORT="443"
fi

# Set trusted domains if not in reverse proxy mode
if [ "$APACHE_PORT" == '443' ]; then
  if [ -z "$ADDITIONAL_TRUSTED_DOMAIN" ]; then
      export TRUSTED_DOMAINS="$NC_DOMAIN"
  else
      export TRUSTED_DOMAINS="$ADDITIONAL_TRUSTED_DOMAIN,$NC_DOMAIN"
  fi
fi

./caddyfile.sh > /tmp/Caddyfile

# Fix the Caddyfile format
caddy fmt --overwrite /tmp/Caddyfile

# Add caddy path
mkdir -p /mnt/data/caddy/

# Fix apache startup
rm -f /usr/local/apache2/logs/httpd.pid

exec "$@"
