#!/bin/bash
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

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
