#!/bin/sh
set -eu

while true; do
    php /var/www/docker-aio/php/src/Cron/cron.php
    sleep 1d
done
