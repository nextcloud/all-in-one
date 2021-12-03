#!/bin/sh
set -eu

while true; do
    sudo -u www-data php /var/www/docker-aio/php/src/Cron/cron.php
    sleep 1d
done
