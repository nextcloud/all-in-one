#!/bin/bash

while true; do
    if [ -f "/mnt/docker-aio-config/data/daily_backup_time" ]; then
        set -x
        BACKUP_TIME="$(head -1 "/mnt/docker-aio-config/data/daily_backup_time")"
        export BACKUP_TIME
        export DAILY_BACKUP=1
        if [ "$(sed -n '2p' "/mnt/docker-aio-config/data/daily_backup_time")" != 'automaticUpdatesAreNotEnabled' ]; then
            export AUTOMATIC_UPDATES=1
        else
            export AUTOMATIC_UPDATES=0
            export START_CONTAINERS=1
        fi
        if [ "$(sed -n '3p' "/mnt/docker-aio-config/data/daily_backup_time")" != 'successNotificationsAreNotEnabled' ]; then
            export SEND_SUCCESS_NOTIFICATIONS=1
        else
            export SEND_SUCCESS_NOTIFICATIONS=0
        fi
        set +x
        if [ -f "/mnt/docker-aio-config/data/daily_backup_running" ]; then
            export LOCK_FILE_PRESENT=1
        else
            export LOCK_FILE_PRESENT=0
        fi
    else
        export BACKUP_TIME="04:00"
        export DAILY_BACKUP=0
        export LOCK_FILE_PRESENT=0
    fi

    # Allow to continue directly if e.g. the mastercontainer was updated. Otherwise wait for the next execution
    if [ "$LOCK_FILE_PRESENT" = 0 ]; then
        while [ "$(date +%H:%M)" != "$BACKUP_TIME" ]; do 
            sleep 30
        done
    fi

    if [ "$DAILY_BACKUP" = 1 ]; then
        bash /daily-backup.sh
    fi

    # Make sure to delete the lock file always
    rm -f "/mnt/docker-aio-config/data/daily_backup_running"

    # Check for updates and send notification if yes on saturdays
    if [ "$(date +%u)" = 6 ]; then
        sudo -E -u www-data php /var/www/docker-aio/php/src/Cron/UpdateNotification.php
    fi

    # Check if AIO is outdated
    sudo -E -u www-data php /var/www/docker-aio/php/src/Cron/OutdatedNotification.php

    # Remove sessions older than 24h
    find "/mnt/docker-aio-config/session/" -mindepth 1 -mmin +1440 -delete

    # Remove nextcloud-aio-domaincheck container
    if sudo -E -u www-data docker ps --format "{{.Names}}" --filter "status=exited" | grep -q "^nextcloud-aio-domaincheck$"; then
        sudo -E -u www-data docker container remove nextcloud-aio-domaincheck
    fi

    # Remove dangling images
    sudo -E -u www-data docker image prune --filter "label=org.label-schema.vendor=Nextcloud" --force

    # Check for available free space
    sudo -E -u www-data php /var/www/docker-aio/php/src/Cron/CheckFreeDiskSpace.php

    # Remove mastercontainer from default bridge network
    if sudo -E -u www-data docker inspect nextcloud-aio-mastercontainer  --format "{{.NetworkSettings.Networks}}" | grep -q "bridge"; then
        sudo -E -u www-data docker network disconnect bridge nextcloud-aio-mastercontainer
    fi

    # Wait 60s so that the whole loop will not be executed again
    sleep 60
done
