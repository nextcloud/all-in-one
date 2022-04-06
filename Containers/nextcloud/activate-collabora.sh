#!/bin/bash

COLLABORA_ACTIVATED=0

while true; do
    if [ "$COLLABORA_ENABLED" != yes ]; then
        # Basically sleep for forever if collabora is not enabled
        sleep 365d
    fi
    if [ "$COLLABORA_ACTIVATED" != 0 ]; then
        # Basically sleep for forever if collabora was activated
        sleep 365d
    fi
    while ! nc -z "$NC_DOMAIN" 443; do
        sleep 5
    done
    echo "Activating collabora config"
    php /var/www/html/occ richdocuments:activate-config
    COLLABORA_ACTIVATED=1
done
