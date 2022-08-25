#!/bin/bash

if [ "$COLLABORA_ENABLED" != yes ]; then
    # Basically sleep for forever if collabora is not enabled
    sleep inf
fi
while ! nc -z "$NC_DOMAIN" 443; do
    sleep 5
done
sleep 10
echo "Activating collabora config..."
php /var/www/html/occ richdocuments:activate-config
sleep inf
