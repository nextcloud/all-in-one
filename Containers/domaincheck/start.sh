#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

if [ -z "$INSTANCE_ID" ]; then
    echo "You need to provide an instance id."
    exit 1
fi

echo "$INSTANCE_ID" > /var/www/domaincheck/index.html

if [ -z "$APACHE_PORT" ]; then
    export APACHE_PORT="443"
fi

CONF_FILE="$(sed "s|ipv6-placeholder|\[::\]:$APACHE_PORT|" /lighttpd.conf)"
echo "$CONF_FILE" > /etc/lighttpd/lighttpd.conf

# shellcheck disable=SC2235
if ([ "$AIO_LOG_LEVEL" = 'debug' ] || [ "$AIO_LOG_LEVEL" = 'info' ]) && ! grep -q debug.log-request-handling /etc/lighttpd/lighttpd.conf; then
    cat << CONF_FILE >> /etc/lighttpd/lighttpd.conf
debug.log-request-handling = "enable"
CONF_FILE
fi

if [ "$AIO_LOG_LEVEL" = 'debug' ] && ! grep -q debug.log-request-header /etc/lighttpd/lighttpd.conf; then
    cat << CONF_FILE >> /etc/lighttpd/lighttpd.conf
debug.log-request-header = "enable"
debug.log-response-header = "enable"
CONF_FILE
fi

# Check config file
lighttpd -tt -f /etc/lighttpd/lighttpd.conf

# Run server
lighttpd -D -f /etc/lighttpd/lighttpd.conf

exec "$@"
