#!/bin/bash

while true; do
    if [ -f "/mnt/docker-aio-config/data/daily_backup_time" ]; then
        set -x
        BACKUP_TIME="$(cat "/mnt/docker-aio-config/data/daily_backup_time")"
        DAILY_BACKUP=1
        set +x
    else
        BACKUP_TIME="04:00"
        DAILY_BACKUP=0
    fi

    if [ -f "/mnt/docker-aio-config/data/daily_backup_running" ]; then
        LOCK_FILE_PRESENT=1
    else
        LOCK_FILE_PRESENT=0
    fi

    # Allow to continue directly if e.g. the mastercontainer was updated. Otherwise wait for the next execution
    if [ "$LOCK_FILE_PRESENT" = 0 ]; then
        while [ "$(date +%H:%M)" != "$BACKUP_TIME" ]; do 
            sleep 30
        done
    fi

    if [ "$DAILY_BACKUP" = 1 ]; then
        echo "Daily backup has started"

        # Delete all active sessions and create a lock file
        # But don't kick out the user if the mastercontainer was just updated since we block the interface either way with the lock file
        if [ "$LOCK_FILE_PRESENT" = 0 ]; then
            rm -f "/mnt/docker-aio-config/session/"*
        fi
        sudo -u www-data touch "/mnt/docker-aio-config/data/daily_backup_running"

        # Check if apache is running/stopped, watchtower is stopped and backupcontainer is stopped
        APACHE_PORT="$(docker inspect nextcloud-aio-apache --format "{{.HostConfig.PortBindings}}" | grep -oP '[0-9]+' | head -1)"
        while docker ps --format "{{.Names}}" | grep -q "^nextcloud-aio-apache$" && ! nc -z nextcloud-aio-apache "$APACHE_PORT"; do
            echo "Waiting for apache to become available"
            sleep 30
        done
        while docker ps --format "{{.Names}}" | grep -q "^nextcloud-aio-watchtower$"; do
            echo "Waiting for watchtower to stop"
            sleep 30
        done
        while docker ps --format "{{.Names}}" | grep -q "^nextcloud-aio-borgbackup$"; do
            echo "Waiting for borgbackup to stop"
            sleep 30
        done

        # Update the mastercontainer
        sudo -u www-data php /var/www/docker-aio/php/src/Cron/UpdateMastercontainer.php

        # Wait for watchtower to stop
        if ! docker ps --format "{{.Names}}" | grep -q "^nextcloud-aio-watchtower$"; then
            echo "Something seems to be wrong: Watchtower should be started at this step."
        else
            while docker ps --format "{{.Names}}" | grep -q "^nextcloud-aio-watchtower$"; do
                echo "Waiting for watchtower to stop"
                sleep 30
            done
        fi

        # Execute the backup itself and some related tasks
        sudo -u www-data php /var/www/docker-aio/php/src/Cron/DailyBackup.php

        # Delete the lock file
        rm -f "/mnt/docker-aio-config/data/daily_backup_running"

        # Wait for the nextcloud container to start and send if the backup was successful
        if ! docker ps --format "{{.Names}}" | grep -q "^nextcloud-aio-nextcloud$"; then
            echo "Something seems to be wrong: Nextcloud should be started at this step."
        else
            while docker ps --format "{{.Names}}" | grep -q "^nextcloud-aio-nextcloud$" && ! nc -z nextcloud-aio-nextcloud 9000; do
                echo "Waiting for the Nextcloud container to start"
                sleep 30
            done
        fi
        sudo -u www-data php /var/www/docker-aio/php/src/Cron/BackupNotification.php

        echo "Daily backup has finished"
    fi

    # Make sure to delete the lock file always
    rm -f "/mnt/docker-aio-config/data/daily_backup_running"

    # Check for updates and send notification if yes
    sudo -u www-data php /var/www/docker-aio/php/src/Cron/UpdateNotification.php

    # Remove sessions older than 24h
    find "/mnt/docker-aio-config/session/" -mindepth 1 -mmin +1440 -delete

    # Remove dangling images
    sudo -u www-data docker image prune -f

    # Wait 60s so that the whole loop will not be executed again
    sleep 60
done
