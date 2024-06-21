#!/bin/bash

nc -z "$NEXTCLOUD_HOST" 9000 || exit 0
nc -z 127.0.0.1 8000 || exit 1
nc -z 127.0.0.1 "$APACHE_PORT" || exit 1
if ! nc -z "$NC_DOMAIN" 443; then
    echo "Could not reach $NC_DOMAIN on port 443."
    exit 1
fi
