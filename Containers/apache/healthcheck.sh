#!/bin/bash

nc -z "$NEXTCLOUD_HOST" 9000 || exit 0
nc -z localhost 8000 || exit 1
nc -z localhost "$APACHE_PORT" || exit 1
if ! nc -z "$NC_DOMAIN" 443; then
    echo "Could not reach $NC_DOMAIN on port 443."
    exit 1
fi
