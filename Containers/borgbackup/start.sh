#!/bin/bash

# Variables
export MOUNT_DIR="/mnt/borgbackup"
export BORG_BACKUP_DIRECTORY="$MOUNT_DIR/borg"  # necessary even when remote to store the aio-lockfile

# Validate BORG_PASSWORD
if [ -z "$BORG_PASSWORD" ] && [ -z "$BACKUP_RESTORE_PASSWORD" ]; then
    echo "Neither BORG_PASSWORD nor BACKUP_RESTORE_PASSWORD are set."
    exit 1
fi

# Export defaults
if [ -n "$BACKUP_RESTORE_PASSWORD" ]; then
    export BORG_PASSPHRASE="$BACKUP_RESTORE_PASSWORD"
else
    export BORG_PASSPHRASE="$BORG_PASSWORD"
fi
export BORG_UNKNOWN_UNENCRYPTED_REPO_ACCESS_IS_OK=yes
export BORG_RELOCATED_REPO_ACCESS_IS_OK=yes
if [ -n "$BORG_REMOTE_PATH" ]; then
    export BORG_REMOTE_PATH
fi
if [ -n "$BORG_REMOTE_REPO" ]; then
    export BORG_REPO="$BORG_REMOTE_REPO"

    # Location to create the borg ssh pub/priv key
    export BORGBACKUP_KEY="/nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/id_borg"

    # Accept any host key the first time connecting to the remote. Strictly speaking should be provided by user but you'd
    # have to be very unlucky to get MitM'ed on your first connection.
    export BORG_RSH="ssh -o StrictHostKeyChecking=accept-new -i $BORGBACKUP_KEY"
else
    export BORG_REPO="$BORG_BACKUP_DIRECTORY"
fi

# Validate BORG_MODE
if [ "$BORG_MODE" != backup ] && [ "$BORG_MODE" != restore ] && [ "$BORG_MODE" != check ] && [ "$BORG_MODE" != "check-repair" ] && [ "$BORG_MODE" != test ]; then
    echo "No correct BORG_MODE mode applied. Valid are 'backup', 'check', 'restore' and 'test'."
    exit 1
fi

export BORG_MODE

# Run the backup script
if ! bash /backupscript.sh; then
    FAILED=1
fi

# Remove lockfile
rm -f "/nextcloud_aio_volumes/nextcloud_aio_database_dump/backup-is-running"

# Get a list of all available borg archives
if borg list &>/dev/null; then
    borg list | grep "nextcloud-aio" | awk -F " " '{print $1","$3,$4}' > "/nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/backup_archives.list"
else
    echo "" > "/nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/backup_archives.list"
fi
chmod +r "/nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/backup_archives.list"

if [ -n "$FAILED" ]; then
    if [ "$BORG_MODE" = backup ]; then
        # Add file to Nextcloud container so that it skips any update the next time
        touch "/nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/skip.update"
        chmod 777 "/nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/skip.update"
    fi
    exit 1
fi

exec "$@"