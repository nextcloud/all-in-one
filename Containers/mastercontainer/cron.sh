#!/bin/sh
set -eux

while true; do
    # Check for updates and send notification if yes
    sudo -u www-data php /var/www/docker-aio/php/src/Cron/cron.php
    # Remove dangling images
    sudo -u www-data docker image prune -f
    sleep 1d
done
