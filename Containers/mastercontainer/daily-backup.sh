#!/bin/bash

echo "Daily backup script has started"

# Daily backup and backup check cannot be run at the same time
if [ "$DAILY_BACKUP" = 1 ] && [ "$CHECK_BACKUP" = 1 ]; then
    echo "Daily backup and backup check cannot be run at the same time. Exiting..."
    exit 1
fi

# Delete all active sessions and create a lock file
# But don't kick out the user if the mastercontainer was just updated since we block the interface either way with the lock file
if [ "$LOCK_FILE_PRESENT" = 0 ] || ! [ -f "/mnt/docker-aio-config/data/daily_backup_running" ]; then
    find "/mnt/docker-aio-config/session/" -mindepth 1 -delete
fi
sudo -u www-data touch "/mnt/docker-aio-config/data/daily_backup_running"

# Check if apache is running/stopped, watchtower is stopped and backupcontainer is stopped
APACHE_PORT="$(docker inspect nextcloud-aio-apache --format "{{.Config.Env}}" | grep -o 'APACHE_PORT=[0-9]\+' | grep -o '[0-9]\+' | head -1)"
if [ -z "$APACHE_PORT" ]; then
    echo "APACHE_PORT is not set which is not expected..."
else
    # Connect mastercontainer to nextcloud-aio network to make sure that nextcloud-aio-apache is reachable
    # Prevent issues like https://github.com/nextcloud/all-in-one/discussions/5222
    docker network connect nextcloud-aio nextcloud-aio-mastercontainer &>/dev/null

    # Wait for apache to start
    while docker ps --format "{{.Names}}" | grep -q "^nextcloud-aio-apache$" && ! nc -z nextcloud-aio-apache "$APACHE_PORT"; do
        echo "Waiting for apache to become available"
        sleep 30
    done
fi
while docker ps --format "{{.Names}}" | grep -q "^nextcloud-aio-watchtower$"; do
    echo "Waiting for watchtower to stop"
    sleep 30
done
while docker ps --format "{{.Names}}" | grep -q "^nextcloud-aio-borgbackup$"; do
    echo "Waiting for borgbackup to stop"
    sleep 30
done

# Update the mastercontainer
if [ "$AUTOMATIC_UPDATES" = 1 ]; then
    echo "Starting mastercontainer update..." 
    echo "(The script might get exited due to that. In order to update all the other containers correctly, you need to run this script with the same settings a second time.)"
    sudo -u www-data php /var/www/docker-aio/php/src/Cron/UpdateMastercontainer.php
fi

# Wait for watchtower to stop
if [ "$AUTOMATIC_UPDATES" = 1 ]; then
    if ! docker ps --format "{{.Names}}" | grep -q "^nextcloud-aio-watchtower$"; then
        echo "Something seems to be wrong: Watchtower should be started at this step."
    fi
    while docker ps --format "{{.Names}}" | grep -q "^nextcloud-aio-watchtower$"; do
        echo "Waiting for watchtower to stop"
        sleep 30
    done
fi

# Stop containers if required
# shellcheck disable=SC2235
if [ "$CHECK_BACKUP" != 1 ] && ([ "$DAILY_BACKUP" != 1 ] || [ "$STOP_CONTAINERS" = 1 ]); then
    echo "Stopping containers..."
    sudo -u www-data php /var/www/docker-aio/php/src/Cron/StopContainers.php
fi

# Execute the backup itself and some related tasks (also stops the containers)
if [ "$DAILY_BACKUP" = 1 ]; then
    echo "Creating daily backup..."
    sudo -u www-data php /var/www/docker-aio/php/src/Cron/CreateBackup.php
    if ! docker ps --format "{{.Names}}" | grep -q "^nextcloud-aio-borgbackup$"; then
        echo "Something seems to be wrong: the borg container should be started at this step."
    fi
    while docker ps --format "{{.Names}}" | grep -q "^nextcloud-aio-borgbackup$"; do
        echo "Waiting for backup container to stop"
        sleep 30
    done
fi

# Execute backup check
if [ "$CHECK_BACKUP" = 1 ]; then
    echo "Starting backup check..."
    sudo -u www-data php /var/www/docker-aio/php/src/Cron/CheckBackup.php
fi

# Start and/or update containers
if [ "$AUTOMATIC_UPDATES" = 1 ]; then
    echo "Starting and updating containers..."
    sudo -u www-data php /var/www/docker-aio/php/src/Cron/StartAndUpdateContainers.php
else
    if [ "$START_CONTAINERS" = 1 ]; then
        echo "Starting containers without updating them..."
        sudo -u www-data php /var/www/docker-aio/php/src/Cron/StartContainers.php
    fi
fi

# Delete the lock file
rm -f "/mnt/docker-aio-config/data/daily_backup_running"

# Send backup notification
# shellcheck disable=SC2235
if [ "$DAILY_BACKUP" = 1 ] && ([ "$AUTOMATIC_UPDATES" = 1 ] || [ "$START_CONTAINERS" = 1 ]); then
    # Wait for the nextcloud container to start and send if the backup was successful
    if ! docker ps --format "{{.Names}}" | grep -q "^nextcloud-aio-nextcloud$"; then
        echo "Something seems to be wrong: Nextcloud should be started at this step."
    else
        while docker ps --format "{{.Names}}" | grep -q "^nextcloud-aio-nextcloud$" && ! nc -z nextcloud-aio-nextcloud 9000; do
            echo "Waiting for the Nextcloud container to start"
            sleep 30
            if [ "$(docker inspect nextcloud-aio-nextcloud --format "{{.State.Restarting}}")" = "true" ]; then
                echo "Nextcloud container restarting. Skipping this check!"
                break
            fi
        done
    fi
    echo "Sending backup notification..."
    sudo -E -u www-data php /var/www/docker-aio/php/src/Cron/BackupNotification.php
fi

echo "Daily backup script has finished"
