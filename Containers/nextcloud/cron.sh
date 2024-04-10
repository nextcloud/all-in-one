#!/bin/bash
wait_for_cron() {
    set -x
    while [ -n "$(pgrep -f /var/www/html/cron.php)" ]; do
        echo "Waiting for cron to stop..."
        sleep 5
    done
    echo "Cronjob successfully exited."
    exit
}

trap wait_for_cron SIGINT SIGTERM

while true; do
    php -f /var/www/html/cron.php &
    sleep 5m &
    wait $!
done
