#!/bin/sh
set -eux

while true; do
    # Check for updates and send notification if yes
    sudo -u www-data php /var/www/docker-aio/php/src/Cron/cron.php
    # Remove dangling images
    sudo -u www-data docker image prune -f
    # Remove dangling volumes
    unset DANGLING_VOLUMES
    DANGLING_VOLUMES="$(sudo -u www-data docker volume ls --filter dangling=true | awk '{print $2}' | grep -v "nextcloud_aio_\|^VOLUME$")"
    mapfile -t DANGLING_VOLUMES <<< "$DANGLING_VOLUMES"
    for volume in "${DANGLING_VOLUMES[@]}"; do
        sudo -u www-data docker volume rm "$volume"
    done
    sleep 1d
done
