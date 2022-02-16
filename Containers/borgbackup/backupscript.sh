#!/bin/bash

# Functions
get_start_time(){
    START_TIME=$(date +%s)
    CURRENT_DATE=$(date --date @"$START_TIME" +"%Y%m%d_%H%M%S")
}
get_expiration_time() {
    END_TIME=$(date +%s)
    END_DATE_READABLE=$(date --date @"$END_TIME" +"%d.%m.%Y - %H:%M:%S")
    DURATION=$((END_TIME-START_TIME))
    DURATION_SEC=$((DURATION % 60))
    DURATION_MIN=$(((DURATION / 60) % 60))
    DURATION_HOUR=$((DURATION / 3600))
    DURATION_READABLE=$(printf "%02d hours %02d minutes %02d seconds" $DURATION_HOUR $DURATION_MIN $DURATION_SEC)
}

# Test if all volumes aren't empty
VOLUME_DIRS="$(find /nextcloud_aio_volumes -mindepth 1 -maxdepth 1 -type d)"
mapfile -t VOLUME_DIRS <<< "$VOLUME_DIRS"
for directory in "${VOLUME_DIRS[@]}"; do
    if ! mountpoint -q "$directory"; then
        echo "$directory is not a mountpoint which is not allowed."
        exit 1
    fi
done

# Check if target is mountpoint
if ! mountpoint -q /mnt/borgbackup; then
    echo "/mnt/borgbackup is not a mountpoint which is not allowed"
    exit 1
fi

# Check if target is empty
if [ "$BORG_MODE" != backup ] && ! [ -f "$BORG_BACKUP_DIRECTORY/config" ]; then
    echo "The repository is empty. cannot perform check or restore."
    exit 1
fi

# Break the borg lock if it exists
if [ -f "$BORG_BACKUP_DIRECTORY/lock.roster" ]; then
    echo "Breaking the borg lock..."
    if ! borg break-lock "$BORG_BACKUP_DIRECTORY"; then
        echo "Could not break the borg lock!"
        exit 1
    fi
fi

# Create lockfile
if [ "$BORG_MODE" = backup ] || [ "$BORG_MODE" = restore ]; then
    touch "/nextcloud_aio_volumes/nextcloud_aio_database_dump/backup-is-running"
fi

# Do the backup
if [ "$BORG_MODE" = backup ]; then

    # Test if important files are present
    if ! [ -f "/nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/configuration.json" ]; then
        echo "configuration.json not present. Cannot perform the backup!"
        exit 1
    elif ! [ -f "/nextcloud_aio_volumes/nextcloud_aio_nextcloud/config/config.php" ]; then
        echo "config.php is missing cannot perform backup"
        exit 1
    elif ! [ -f "/nextcloud_aio_volumes/nextcloud_aio_database_dump/database-dump.sql" ]; then
        echo "database-dump is missing. cannot perform backup"
        exit 1
    fi

    # Test that nothing is empty
    for directory in "${VOLUME_DIRS[@]}"; do
        if [ -z "$(ls -A "$directory")" ]; then
            echo "$directory is empty which is not allowed."
            exit 1
        fi
    done

    if [ -f "/nextcloud_aio_volumes/nextcloud_aio_database_dump/export.failed" ]; then
        echo "Database export failed the last time. Most likely was the export time not high enough."
        echo "Cannot create a backup now."
        echo "Please report this to https://github.com/nextcloud/all-in-one/issues. Thanks!"
        exit 1
    fi

    # Create backup folder
    mkdir -p "$BORG_BACKUP_DIRECTORY"

    # Initialize the repository if the target is empty
    if ! [ -f "$BORG_BACKUP_DIRECTORY/config" ]; then
        # Don't initialize if already initialized
        if [ -f "/nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/borg.config" ]; then
            echo "Cannot initialize a new repository as that was already done at least one time."
            exit 1
        fi

        echo "initializing repository..."
        if ! borg init --debug --encryption=repokey-blake2 "$BORG_BACKUP_DIRECTORY"; then
            echo "Could not initialize borg repository."
            rm -f "$BORG_BACKUP_DIRECTORY/config"
            exit 1
        fi
        borg config "$BORG_BACKUP_DIRECTORY" additional_free_space 2G

        # Fix too large Borg cache
        # https://borgbackup.readthedocs.io/en/stable/faq.html#the-borg-cache-eats-way-too-much-disk-space-what-can-i-do
        BORG_ID="$(borg config "$BORG_BACKUP_DIRECTORY" id)"
        rm -r "/root/.cache/borg/$BORG_ID/chunks.archive.d"
        touch "/root/.cache/borg/$BORG_ID/chunks.archive.d"

        # Make a backup from the borg config file
        if ! [ -f "$BORG_BACKUP_DIRECTORY/config" ]; then
            echo "The borg config file wasn't created. Something is wrong."
            exit 1
        fi
        rm -f "/nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/borg.config"
        if ! cp "$BORG_BACKUP_DIRECTORY/config" "/nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/borg.config"; then
            echo "Could not copy config file to second place. Cannot perform backup."
            exit 1
        fi

        echo "Repository successfully initialized."
    fi

    # Perform backup
    echo "Performing backup..."

    # Borg options
    # auto,zstd compression seems to has the best ratio based on:
    # https://forum.level1techs.com/t/optimal-compression-for-borg-backups/145870/6
    BORG_OPTS=(--stats --progress --compression "auto,zstd" --exclude-caches --checkpoint-interval 86400)

    # Create the backup
    echo "Starting the backup..."
    get_start_time
    if ! borg create "${BORG_OPTS[@]}" "$BORG_BACKUP_DIRECTORY::$CURRENT_DATE-nextcloud-aio" "/nextcloud_aio_volumes/"; then
        echo "Deleting the failed backup archive..."
        borg delete --stats --progress "$BORG_BACKUP_DIRECTORY::$CURRENT_DATE-nextcloud-aio"
        echo "Backup failed!"
        exit 1
    fi

    # Remove the update skip file because the backup was successful
    rm -f "/nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/skip.update"

    # Prune options
    BORG_PRUNE_OPTS=(--stats --progress --keep-within=7d --keep-weekly=4 --keep-monthly=6 "$BORG_BACKUP_DIRECTORY")

    # Prune archives
    echo "Pruning the archives..."
    if ! borg prune --prefix '*_*-nextcloud-aio' "${BORG_PRUNE_OPTS[@]}"; then
        echo "Failed to prune archives!"
        exit 1
    fi

    # Inform user
    get_expiration_time
    echo "Backup finished successfully on $END_DATE_READABLE ($DURATION_READABLE)"
    exit 0
fi

# Do the restore
if [ "$BORG_MODE" = restore ]; then
    get_start_time

    # Perform the restore
    if [ -n "$SELECTED_RESTORE_TIME" ]; then
        SELECTED_ARCHIVE="$(borg list "$BORG_BACKUP_DIRECTORY" | grep "nextcloud-aio" | grep "$SELECTED_RESTORE_TIME" | awk -F " " '{print $1}' | head -1)"
    else
        SELECTED_ARCHIVE="$(borg list "$BORG_BACKUP_DIRECTORY" | grep "nextcloud-aio" | awk -F " " '{print $1}' | sort -r | head -1)"
    fi
    echo "Restoring '$SELECTED_ARCHIVE'..."
    mkdir -p /tmp/borg
    if ! borg mount "$BORG_BACKUP_DIRECTORY::$SELECTED_ARCHIVE" /tmp/borg; then
        echo "Could not mount the backup!"
        exit 1
    fi
    if ! rsync --stats --archive --human-readable -vv --delete \
    --exclude "nextcloud_aio_mastercontainer/session/"** \
    --exclude "nextcloud_aio_mastercontainer/certs/"** \
    /tmp/borg/nextcloud_aio_volumes/ /nextcloud_aio_volumes; then
        echo "Something failed while restoring the boot partition."
        umount /tmp/borg
        exit 1
    fi
    umount /tmp/borg

    # TODO: reset fetchtimes in configuration.json so that it doesn't get the latest directly...

    # Inform user
    get_expiration_time
    echo "Restore finished successfully on $END_DATE_READABLE ($DURATION_READABLE)"

    # Add file to Nextcloud container so that it skips any update the next time
    touch "/nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/skip.update"
    chmod 777 "/nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/skip.update"

    # Set backup-mode to restore since it was a restore
    sed -i 's/"backup-mode":"[a-z]\+"/"backup-mode":"restore"/g' /nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/configuration.json

    exit 0
fi

# Do the Backup check
if [ "$BORG_MODE" = check ]; then
    get_start_time
    echo "Checking the backup integity..."

    # Perform the check
    if ! borg check --verify-data --progress "$BORG_BACKUP_DIRECTORY"; then
        echo "Some errors were found while checking the backup integrity!"
        exit 1
    fi

    # Inform user
    get_expiration_time
    echo "Check finished successfully on $END_DATE_READABLE ($DURATION_READABLE)"
    exit 0
fi
