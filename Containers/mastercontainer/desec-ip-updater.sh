#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

while true; do
    # Update deSEC DNS IP records if the public IP changed (no-op when deSEC is not configured)
    if [ -f "/mnt/docker-aio-config/data/configuration.json" ]; then
        php /var/www/docker-aio/php/src/Cron/UpdateDesecIp.php
    fi
    sleep 600
done
