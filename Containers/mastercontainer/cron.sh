#!/bin/sh
set -eux

while true; do
    # Check for updates and send notification if yes
    sudo -u www-data php /var/www/docker-aio/php/src/Cron/cron.php
    # Remove dangling images
    sudo -u www-data docker image prune -f
    # Remove sessions older than 24h
    find "/mnt/docker-aio-config/session/" -mindepth 1 -mmin +1440 -delete
    sleep 1d
done
