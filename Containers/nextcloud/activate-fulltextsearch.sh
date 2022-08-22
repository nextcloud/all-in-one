#!/bin/bash

if [ "$FULLTEXTSEARCH_ENABLED" != yes ]; then
    # Basically sleep for forever if fulltextsearch is not enabled
    sleep 365d
fi
echo "Activating fulltextsearch..."
php /var/www/html/occ fulltextsearch:live -q
sleep 365d
