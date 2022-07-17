#!/bin/bash

if [ -z "$INSTANCE_ID" ]; then
    echo "You need to provide an instance id."
    exit 1
fi

echo "$INSTANCE_ID" > /var/www/domaincheck/index.html

if [ -z "$APACHE_PORT" ]; then
    export APACHE_PORT="443"
fi

CONF_FILE="$(sed "s|ipv6-placeholder|\[::\]:$APACHE_PORT|" /etc/lighttpd/lighttpd.conf)"
echo "$CONF_FILE" > /etc/lighttpd/lighttpd.conf

# Check config file
lighttpd -tt -f /etc/lighttpd/lighttpd.conf

# Run server
lighttpd -D -f /etc/lighttpd/lighttpd.conf

exec "$@"
