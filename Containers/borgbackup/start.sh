#!/bin/bash

# Validate BORG_PASSWORD
if [ -z "$BORG_PASSWORD" ]; then
    echo "BORG_PASSWORD is not allowed to be empty."
    exit 1
fi

export BORG_PASSWORD

# Validate BORG_MODE
if [ "$BORG_MODE" != backup ] && [ "$BORG_MODE" != restore ] && [ "$BORG_MODE" != check ]; then
    echo "No correct BORG_MODE mode applied. Valid are 'backup' and 'restore'."
    exit 1
fi

export BORG_MODE

# Run the backup script
if ! bash /backupscript.sh; then
    FAILED=1
fi

# Remove lockfile
rm -f "/nextcloud_aio_volumes/nextcloud_aio_database_dump/backup-is-running"

if [ -n "$FAILED" ]; then
    exit 1
fi

exec "$@"