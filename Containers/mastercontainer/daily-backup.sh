#!/bin/bash

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
if [ "$AUTOMATIC_UPDATES" = 1 ]; then
    sudo -u www-data php /var/www/docker-aio/php/src/Cron/UpdateMastercontainer.php
fi

# Wait for watchtower to stop
if [ "$AUTOMATIC_UPDATES" = 1 ] && ! docker ps --format "{{.Names}}" | grep -q "^nextcloud-aio-watchtower$"; then
    echo "Something seems to be wrong: Watchtower should be started at this step."
else
    while docker ps --format "{{.Names}}" | grep -q "^nextcloud-aio-watchtower$"; do
        echo "Waiting for watchtower to stop"
        sleep 30
    done
fi

# Stop containers if required
if [ "$DAILY_BACKUP" != 1 ] || [ "$STOP_CONTAINERS" = 1 ]; then
    sudo -u www-data php /var/www/docker-aio/php/src/Cron/StopContainers.php
fi

# Execute the backup itself and some related tasks (also stops the containers)
if [ "$DAILY_BACKUP" = 1 ]; then
    sudo -u www-data php /var/www/docker-aio/php/src/Cron/CreateBackup.php
fi

# Start and/or update containers
if [ "$AUTOMATIC_UPDATES" = 1 ]; then
    sudo -u www-data php /var/www/docker-aio/php/src/Cron/StartAndUpdateContainers.php
else
    if [ "$START_CONTAINERS" = 1 ]; then
        sudo -u www-data php /var/www/docker-aio/php/src/Cron/StartContainers.php
    fi
fi

# Delete the lock file
rm -f "/mnt/docker-aio-config/data/daily_backup_running"

if [ "$DAILY_BACKUP" = 1 ]; then
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
fi

echo "Daily backup has finished"
