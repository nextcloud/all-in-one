#!/bin/bash

if [ -z "$INSTANCE_ID" ]; then
    echo "You need to provide an instance id."
    exit 1
fi

echo "$INSTANCE_ID" > /var/www/domaincheck/index.html

# Check config file
lighttpd -tt -f /etc/lighttpd/lighttpd.conf

# Run server
lighttpd -D -f /etc/lighttpd/lighttpd.conf

exec "$@"
