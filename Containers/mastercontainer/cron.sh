#!/bin/sh
set -eux

while true; do
    sudo -u www-data php /var/www/docker-aio/php/src/Cron/cron.php
    sudo -u www-data docker image prune -f
    sudo -u www-data docker volume prune -f
    sleep 1d
done
