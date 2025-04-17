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
# Test if default volumes are there
DEFAULT_VOLUMES=(nextcloud_aio_apache nextcloud_aio_nextcloud nextcloud_aio_database nextcloud_aio_database_dump nextcloud_aio_elasticsearch nextcloud_aio_nextcloud_data nextcloud_aio_mastercontainer)
for volume in "${DEFAULT_VOLUMES[@]}"; do
    if ! mountpoint -q "/nextcloud_aio_volumes/$volume"; then
        echo "$volume is missing which is not intended."
        exit 1
    fi
done

# Check if target is mountpoint
if [ -z "$BORG_REMOTE_REPO" ] && ! mountpoint -q "$MOUNT_DIR"; then
    echo "$MOUNT_DIR is not a mountpoint which is not allowed."
    exit 1
fi

# Check if repo is uninitialized
if [ "$BORG_MODE" != backup ] && [ "$BORG_MODE" != test ] && ! borg info > /dev/null; then
    if [ -n "$BORG_REMOTE_REPO" ]; then
        echo "The repository is uninitialized or cannot connect to remote. Cannot perform check or restore."
    else
        echo "The repository is uninitialized. Cannot perform check or restore."
    fi
    exit 1
fi

# Do not continue if this file exists (needed for simple external blocking)
if [ -z "$BORG_REMOTE_REPO" ] && [ -f "$BORG_BACKUP_DIRECTORY/aio-lockfile" ]; then
    echo "Not continuing because aio-lockfile exists – it seems like a script is externally running which is locking the backup archive."
    echo "If this should not be the case, you can fix this by deleting the 'aio-lockfile' file from the backup archive directory."
    exit 1
fi

# Create lockfile
if [ "$BORG_MODE" = backup ] || [ "$BORG_MODE" = restore ]; then
    touch "/nextcloud_aio_volumes/nextcloud_aio_database_dump/backup-is-running"
fi

if [ -n "$BORG_REMOTE_REPO" ] && ! [ -f "$BORGBACKUP_KEY" ]; then
    echo "First run, creating borg ssh key"
    ssh-keygen  -f "$BORGBACKUP_KEY" -N ""
    echo "You should configure the remote to accept this public key"
fi
if [ -n "$BORG_REMOTE_REPO" ] && [ -f "$BORGBACKUP_KEY.pub" ]; then
    echo "Your public ssh key for borgbackup is: $(cat "$BORGBACKUP_KEY.pub")"
fi

# Do the backup
if [ "$BORG_MODE" = backup ]; then

    # Test if important files are present
    if ! [ -f "/nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/configuration.json" ]; then
        echo "configuration.json not present. Cannot perform the backup!"
        exit 1
    elif ! [ -f "/nextcloud_aio_volumes/nextcloud_aio_nextcloud/config/config.php" ]; then
        echo "config.php is missing. Cannot perform backup!"
        exit 1
    elif ! [ -f "/nextcloud_aio_volumes/nextcloud_aio_database_dump/database-dump.sql" ]; then
        echo "database-dump is missing. Cannot perform backup!"
        echo "Please check the database container logs!"
        exit 1
    elif ! [ -f "/nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/.ocdata" ] && ! [ -f "/nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/.ncdata" ]; then
        echo "The .ncdata or .ocdata file is missing in Nextcloud datadir which means it is invalid!"
        echo "Is the drive where the datadir is located on still mounted?"
        exit 1
    fi

    # Test that default volumes are not empty
    for volume in "${DEFAULT_VOLUMES[@]}"; do
        if [ -z "$(ls -A "/nextcloud_aio_volumes/$volume")" ] && [ "$volume" != "nextcloud_aio_elasticsearch" ]; then
            echo "/nextcloud_aio_volumes/$volume is empty which should not happen!"
            exit 1
        fi
    done

    if [ -f "/nextcloud_aio_volumes/nextcloud_aio_database_dump/export.failed" ]; then
        echo "Cannot create a backup now."
        echo "Reason is that the database export failed the last time."
        echo "Most likely was the database container not correctly shut down via the AIO interface."
        echo ""
        echo "You might want to try the database export again manually by running the three commands:"
        echo "sudo docker start nextcloud-aio-database"
        echo "sleep 10"
        echo "sudo docker stop nextcloud-aio-database -t 1800"
        echo ""
        echo "Afterwards try to create a backup again and it should hopefully work."
        echo "If it should still fail, feel free to report this to https://github.com/nextcloud/all-in-one/issues and post the database container logs and the borgbackup container logs into the thread. Thanks!"
        exit 1
    fi

    if [ -z "$BORG_REMOTE_REPO" ]; then
        # Create backup folder
        mkdir -p "$BORG_BACKUP_DIRECTORY"
    fi

    # Initialize the repository if can't get info from target
    if ! borg info > /dev/null; then
        # Don't initialize if already initialized
        if [ -f "/nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/borg.config" ]; then
            if [ -n "$BORG_REMOTE_REPO" ]; then
                echo "Borg could not get info from the remote repo."
                echo "This might be a failure to connect to the remote server. See the above borg info output for details."
            else
                echo "Borg could not get info from the targeted directory."
                echo "This might happen if the targeted directory is located on an external drive and the drive not connected anymore. You should check this."
            fi
            echo "If you instead want to initialize a new backup repository, you may delete the 'borg.config' file that is stored in the mastercontainer volume manually, which will allow you to initialize a new borg repository in the chosen directory:"
            echo "sudo docker exec nextcloud-aio-mastercontainer rm /mnt/docker-aio-config/data/borg.config"
            exit 1
        fi

        echo "Initializing repository..."
        NEW_REPOSITORY=1
        if ! borg init --debug --encryption=repokey-blake2; then
            echo "Could not initialize borg repository."
            if [ -z "$BORG_REMOTE_REPO" ]; then
                # Originally we checked for presence of the config file instead of calling `borg info`. Likely `borg info`
                # will error on a partially initialized repo, so this line is probably no longer necessary
                rm -f "$BORG_BACKUP_DIRECTORY/config"
            fi
            exit 1
        fi

        if [ -z "$BORG_REMOTE_REPO" ]; then
            # borg config only works for local repos; it's up to the remote to ensure the disk isn't full
            borg config :: additional_free_space 2G

            # Fix too large Borg cache
            # https://borgbackup.readthedocs.io/en/stable/faq.html#the-borg-cache-eats-way-too-much-disk-space-what-can-i-do
            BORG_ID="$(borg config :: id)"
            rm -r "/root/.cache/borg/$BORG_ID/chunks.archive.d"
            touch "/root/.cache/borg/$BORG_ID/chunks.archive.d"
        fi

        if ! borg info > /dev/null; then
            echo "Borg can't get info from the repo it created. Something is wrong."
            exit 1
        fi

        rm -f "/nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/borg.config"
        if [ -n "$BORG_REMOTE_REPO" ]; then
            # `borg config` does not support remote repos so instead create a dummy file and rely on the remote to avoid
            # corruption of the config file (which contains the encryption key). We don't actually use the contents of
            # this file anywhere, so a touch is all we need so we remember we already initialized the repo.
            touch "/nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/borg.config"
        else
            # Make a backup from the borg config file
            if ! cp "$BORG_BACKUP_DIRECTORY/config" "/nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/borg.config"; then
                echo "Could not copy config file to second place. Cannot perform backup."
                exit 1
            fi
        fi

        echo "Repository successfully initialized."
    fi

    # Perform backup
    echo "Performing backup..."

    # Borg options
    # auto,zstd compression seems to has the best ratio based on:
    # https://forum.level1techs.com/t/optimal-compression-for-borg-backups/145870/6
    BORG_OPTS=(-v --stats --compression "auto,zstd" --progress)

    # Exclude the nextcloud log and audit log for GDPR reasons
    BORG_EXCLUDE=(--exclude "/nextcloud_aio_volumes/nextcloud_aio_nextcloud/data/nextcloud.log*" --exclude "/nextcloud_aio_volumes/nextcloud_aio_nextcloud/data/audit.log" --exclude "/nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/lost+found")
    BORG_INCLUDE=()

    # Exclude datadir if .noaiobackup file was found
    # shellcheck disable=SC2144
    if [ -f "/nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/.noaiobackup" ]; then
        BORG_EXCLUDE+=(--exclude "/nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/")
        BORG_INCLUDE+=(--pattern="+/nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/.noaiobackup")
        echo "⚠️⚠️⚠️ '.noaiobackup' file was found in Nextclouds data directory. Excluding the data directory from backup!"
    # Exclude preview folder if .noaiobackup file was found
    elif [ -f /nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/appdata_*/preview/.noaiobackup ]; then
        BORG_EXCLUDE+=(--exclude "/nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/appdata_*/preview/")
        BORG_INCLUDE+=(--pattern="+/nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/appdata_*/preview/.noaiobackup")
        echo "⚠️⚠️⚠️ '.noaiobackup' file was found in the preview directory. Excluding the preview directory from backup!"
    fi

    # Make sure that there is always a borg.config file before creating a new backup
    if ! [ -f "/nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/borg.config" ]; then
        echo "Did not find borg.config file in the mastercontainer volume."
        echo "Cannot create a backup as this is wrong."
        exit 1
    fi

    # Create the backup
    echo "Starting the backup..."
    get_start_time
    if ! borg create "${BORG_OPTS[@]}" "${BORG_INCLUDE[@]}" "${BORG_EXCLUDE[@]}" "::$CURRENT_DATE-nextcloud-aio" "/nextcloud_aio_volumes/" --exclude-from /borg_excludes; then
        echo "Deleting the failed backup archive..."
        borg delete --stats "::$CURRENT_DATE-nextcloud-aio"
        echo "Backup failed!"
        echo "You might want to check the backup integrity via the AIO interface."
        if [ "$NEW_REPOSITORY" = 1 ]; then
            echo "Deleting borg.config file so that you can choose a different location for the backup."
            rm "/nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/borg.config"
        fi
        exit 1
    fi

    # Remove the update skip file because the backup was successful
    rm -f "/nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/skip.update"

    # Prune options
    read -ra BORG_PRUNE_OPTS <<< "$BORG_RETENTION_POLICY"
    echo "BORG_PRUNE_OPTS are ${BORG_PRUNE_OPTS[*]}"

    # Prune archives
    echo "Pruning the archives..."
    if ! borg prune --stats --glob-archives '*_*-nextcloud-aio' "${BORG_PRUNE_OPTS[@]}"; then
        echo "Failed to prune archives!"
        exit 1
    fi

    # Compact archives
    echo "Compacting the archives..."
    if ! borg compact; then
        echo "Failed to compact archives!"
        exit 1
    fi

    # Back up additional directories of the host
    if [ "$ADDITIONAL_DIRECTORIES_BACKUP" = 'yes' ]; then
        if [ -d "/docker_volumes/" ]; then
            DOCKER_VOLUME_DIRS="$(find /docker_volumes -mindepth 1 -maxdepth 1 -type d)"
            mapfile -t DOCKER_VOLUME_DIRS <<< "$DOCKER_VOLUME_DIRS"
            for directory in "${DOCKER_VOLUME_DIRS[@]}"; do
                if [ -z "$(ls -A "$directory")" ]; then
                    echo "$directory is empty which is not allowed."
                    exit 1
                fi
            done
            echo "Starting the backup for additional volumes..."
            if ! borg create "${BORG_OPTS[@]}" "::$CURRENT_DATE-additional-docker-volumes" "/docker_volumes/"; then
                echo "Deleting the failed backup archive..."
                borg delete --stats "::$CURRENT_DATE-additional-docker-volumes"
                echo "Backup of additional docker-volumes failed!"
                exit 1
            fi
            echo "Pruning additional volumes..."
            if ! borg prune --stats --glob-archives '*_*-additional-docker-volumes' "${BORG_PRUNE_OPTS[@]}"; then
                echo "Failed to prune additional docker-volumes archives!"
                exit 1
            fi
            echo "Compacting additional volumes..."
            if ! borg compact; then
                echo "Failed to compact additional docker-volume archives!"
                exit 1
            fi
        fi
        if [ -d "/host_mounts/" ]; then
            EXCLUDED_DIRECTORIES=(home/*/.cache root/.cache var/cache lost+found run var/run dev tmp sys proc)
            # Exclude borg backup cache
            EXCLUDED_DIRECTORIES+=(var/lib/docker/volumes/nextcloud_aio_backup_cache/_data)
            # Exclude target directory
            if [ -n "$BORGBACKUP_HOST_LOCATION" ] && [ "$BORGBACKUP_HOST_LOCATION" != "nextcloud_aio_backupdir" ]; then
                EXCLUDED_DIRECTORIES+=("$BORGBACKUP_HOST_LOCATION")
            fi
            for directory in "${EXCLUDED_DIRECTORIES[@]}"
            do
                EXCLUDE_DIRS+=(--exclude "/host_mounts/$directory/")
            done
            echo "Starting the backup for additional host mounts..."
            if ! borg create "${BORG_OPTS[@]}" "${EXCLUDE_DIRS[@]}" "::$CURRENT_DATE-additional-host-mounts" "/host_mounts/"; then
                echo "Deleting the failed backup archive..."
                borg delete --stats "::$CURRENT_DATE-additional-host-mounts"
                echo "Backup of additional host-mounts failed!"
                exit 1
            fi
            echo "Pruning additional host mounts..."
            if ! borg prune --stats --glob-archives '*_*-additional-host-mounts' "${BORG_PRUNE_OPTS[@]}"; then
                echo "Failed to prune additional host-mount archives!"
                exit 1
            fi
            echo "Compacting additional host mounts..."
            if ! borg compact; then
                echo "Failed to compact additional host-mount archives!"
                exit 1
            fi
        fi
    fi

    # Inform user
    get_expiration_time
    echo "Backup finished successfully on $END_DATE_READABLE ($DURATION_READABLE)."
    if [ -f "/nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/update.failed" ]; then
        echo "However a Nextcloud update failed. So reporting that the backup failed which will skip any update attempt the next time."
        echo "Please restore a backup from before the failed Nextcloud update attempt."
        exit 1
    fi
    exit 0
fi

# Do the restore
if [ "$BORG_MODE" = restore ]; then
    get_start_time

    # Pick archive to restore
    if [ -n "$SELECTED_RESTORE_TIME" ]; then
        SELECTED_ARCHIVE="$(borg list | grep "nextcloud-aio" | grep "$SELECTED_RESTORE_TIME" | awk -F " " '{print $1}' | head -1)"
    else
        SELECTED_ARCHIVE="$(borg list | grep "nextcloud-aio" | awk -F " " '{print $1}' | sort -r | head -1)"
    fi
    echo "Restoring '$SELECTED_ARCHIVE'..."

    ADDITIONAL_RSYNC_EXCLUDES=()
    ADDITIONAL_BORG_EXCLUDES=()
    ADDITIONAL_FIND_EXCLUDES=()
    # Exclude datadir if .noaiobackup file was found
    # shellcheck disable=SC2144
    if [ -f "/nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/.noaiobackup" ]; then
        # Keep these 3 in sync. Beware, the pattern syntax and the paths differ
        ADDITIONAL_RSYNC_EXCLUDES=(--exclude "nextcloud_aio_nextcloud_data/**")
        ADDITIONAL_BORG_EXCLUDES=(--exclude "sh:nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/**")
        ADDITIONAL_FIND_EXCLUDES=(-o -regex 'nextcloud_aio_volumes/nextcloud_aio_nextcloud_data\(/.*\)?')
        echo "⚠️⚠️⚠️ '.noaiobackup' file was found in Nextclouds data directory. Excluding the data directory from restore!"
        echo "You might run into problems due to this afterwards as potentially this makes the directory go out of sync with the database."
        echo "You might be able to fix this by running 'occ files:scan --all' and 'occ maintenance:repair' and 'occ files:scan-app-data' after the restore."
        echo "See https://github.com/nextcloud/all-in-one#how-to-run-occ-commands"
    # Exclude previews from restore if selected to speed up process or exclude preview folder if .noaiobackup file was found
    elif [ -n "$RESTORE_EXCLUDE_PREVIEWS" ] || [ -f /nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/appdata_*/preview/.noaiobackup ]; then
        # Keep these 3 in sync. Beware, the pattern syntax and the paths differ
        ADDITIONAL_RSYNC_EXCLUDES=(--exclude "nextcloud_aio_nextcloud_data/appdata_*/preview/**")
        ADDITIONAL_BORG_EXCLUDES=(--exclude "sh:nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/appdata_*/preview/**")
        ADDITIONAL_FIND_EXCLUDES=(-o -regex 'nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/appdata_[^/]*/preview\(/.*\)?')
        echo "⚠️⚠️⚠️ Excluding previews from restore!"
        echo "You might run into problems due to this afterwards as potentially this makes the directory go out of sync with the database."
        echo "You might be able to fix this by running 'occ files:scan-app-data preview' after the restore."
        echo "See https://github.com/nextcloud/all-in-one#how-to-run-occ-commands"
    fi

    # Save Additional Backup dirs
    if [ -f "/nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/additional_backup_directories" ]; then
        ADDITIONAL_BACKUP_DIRECTORIES="$(cat /nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/additional_backup_directories)"
    fi

    # Save daily backup time
    if [ -f "/nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/daily_backup_time" ]; then
        DAILY_BACKUPTIME="$(cat /nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/daily_backup_time)"
    fi

    # Save current aio password
    AIO_PASSWORD="$(jq '.password' /nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/configuration.json)"

    # Save current backup location vars
    BORG_LOCATION="$(jq '.borg_backup_host_location' /nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/configuration.json)"
    REMOTE_REPO="$(jq '.borg_remote_repo' /nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/configuration.json)"

    # Save current nextcloud datadir
    if grep -q '"nextcloud_datadir":' /nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/configuration.json; then
        NEXTCLOUD_DATADIR="$(jq '.nextcloud_datadir' /nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/configuration.json)"
    else
        NEXTCLOUD_DATADIR='""'
    fi

    if [ -z "$BORG_REMOTE_REPO" ]; then
        mkdir -p /tmp/borg
        if ! borg mount "::$SELECTED_ARCHIVE" /tmp/borg; then
            echo "Could not mount the backup!"
            exit 1
        fi

        # Restore everything except the configuration file
        #
        # These exclude patterns need to be kept in sync with the borg_excludes file and the find excludes in this file,
        # which use a different syntax (patterns appear in 3 places in total)
        if ! rsync --stats --archive --human-readable -vv --delete \
        --exclude "nextcloud_aio_apache/caddy/**" \
        --exclude "nextcloud_aio_mastercontainer/caddy/**" \
        --exclude "nextcloud_aio_nextcloud/data/nextcloud.log*" \
        --exclude "nextcloud_aio_nextcloud/data/audit.log" \
        --exclude "nextcloud_aio_mastercontainer/certs/**" \
        --exclude "nextcloud_aio_mastercontainer/data/configuration.json" \
        --exclude "nextcloud_aio_mastercontainer/data/daily_backup_running" \
        --exclude "nextcloud_aio_mastercontainer/data/session_date_file" \
        --exclude "nextcloud_aio_mastercontainer/session/**" \
        --exclude "nextcloud_aio_nextcloud_data/lost+found" \
        "${ADDITIONAL_RSYNC_EXCLUDES[@]}" \
        /tmp/borg/nextcloud_aio_volumes/ /nextcloud_aio_volumes/; then
            RESTORE_FAILED=1
            echo "Something failed while restoring from backup."
        fi

        # Restore the configuration file
        if ! rsync --archive --human-readable -vv \
                /tmp/borg/nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/configuration.json \
                /nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/configuration.json; then
            RESTORE_FAILED=1
            echo "Something failed while restoring the configuration.json."
        fi

        if ! umount /tmp/borg; then
            echo "Failed to unmount the borg archive but should still be able to restore successfully"
        fi
    else
        # Restore nearly everything
        #
        # borg mount is really slow for remote repos (did not check whether it's slow for local repos too),
        # using extract to /tmp would require temporarily storing a second copy of the data.
        # So instead extract directly on top of the destination with exclude patterns for the config, but
        # then we do still need to delete local files which are not present in the archive.
        #
        # Older backups may still contain files we've since excluded, so we have to exclude on extract as well.
        cd /  # borg extract has no destination arg and extracts to CWD
        if ! borg extract "::$SELECTED_ARCHIVE" --progress --exclude-from /borg_excludes "${ADDITIONAL_BORG_EXCLUDES[@]}" --pattern '+nextcloud_aio_volumes/**'
        then
            RESTORE_FAILED=1
            echo "Failed to extract backup archive."
        else
            # Delete files/dirs present locally, but not in the backup archive, excluding conf files
            # https://unix.stackexchange.com/a/759341
            # This comm does not support -z, but I doubt any file names would have \n in them
            #
            # These find patterns need to be kept in sync with the borg_excludes file and the rsync excludes in this
            # file, which use a different syntax (patterns appear in 3 places in total)
            echo "Deleting local files which do not exist in the backup"
            if ! find nextcloud_aio_volumes \
                    -not \( \
                        -path nextcloud_aio_volumes/nextcloud_aio_apache/caddy \
                        -o -path "nextcloud_aio_volumes/nextcloud_aio_apache/caddy/*" \
                        -o -path nextcloud_aio_volumes/nextcloud_aio_mastercontainer/caddy \
                        -o -path "nextcloud_aio_volumes/nextcloud_aio_mastercontainer/caddy/*" \
                        -o -path nextcloud_aio_volumes/nextcloud_aio_mastercontainer/certs \
                        -o -path "nextcloud_aio_volumes/nextcloud_aio_mastercontainer/certs/*" \
                        -o -path nextcloud_aio_volumes/nextcloud_aio_mastercontainer/session \
                        -o -path "nextcloud_aio_volumes/nextcloud_aio_mastercontainer/session/*" \
                        -o -path "nextcloud_aio_volumes/nextcloud_aio_nextcloud/data/nextcloud.log*" \
                        -o -path nextcloud_aio_volumes/nextcloud_aio_nextcloud/data/audit.log \
                        -o -path nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/daily_backup_running \
                        -o -path nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/session_date_file \
                        -o -path "nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/id_borg*" \
                        -o -path "nextcloud_aio_nextcloud_data/lost+found" \
                        "${ADDITIONAL_FIND_EXCLUDES[@]}" \
                    \) \
                    | LC_ALL=C sort \
                    | LC_ALL=C comm -23 - \
                        <(borg list "::$SELECTED_ARCHIVE" --short --exclude-from /borg_excludes  --pattern '+nextcloud_aio_volumes/**' | LC_ALL=C sort) \
                    > /tmp/local_files_not_in_backup
            then
                RESTORE_FAILED=1
                echo "Failed to delete local files not in backup archive."
            else
                # More robust than e.g. xargs as I got a ~"args line too long" error while testing that, but it's slower
                # https://stackoverflow.com/a/21848934
                while IFS= read -r file
                do rm -vrf -- "$file" || DELETE_FAILED=1
                done < /tmp/local_files_not_in_backup

                if [ "$DELETE_FAILED" = 1 ]; then
                    RESTORE_FAILED=1
                    echo "Failed to delete (some) local files not in backup archive."
                fi
            fi
        fi
    fi

    # Set backup-mode to restore since it was a restore
    CONTENTS="$(jq '."backup-mode" = "restore"' /nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/configuration.json)"
    echo -E "${CONTENTS}" > /nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/configuration.json

    # Reset the backup location vars to the currently used one
    CONTENTS="$(jq ".borg_backup_host_location = $BORG_LOCATION" /nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/configuration.json)"
    echo -E "${CONTENTS}" > /nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/configuration.json
    CONTENTS="$(jq ".borg_remote_repo = $REMOTE_REPO" /nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/configuration.json)"
    echo -E "${CONTENTS}" > /nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/configuration.json

    # Reset the AIO password to the currently used one
    CONTENTS="$(jq ".password = $AIO_PASSWORD" /nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/configuration.json)"
    echo -E "${CONTENTS}" > /nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/configuration.json

    # Reset the datadir to the one that was used for the restore
    CONTENTS="$(jq ".nextcloud_datadir = $NEXTCLOUD_DATADIR" /nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/configuration.json)"
    echo -E "${CONTENTS}" > /nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/configuration.json

    # Reset the additional backup directories
    if [ -n "$ADDITIONAL_BACKUP_DIRECTORIES" ]; then
        echo "$ADDITIONAL_BACKUP_DIRECTORIES" > "/nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/additional_backup_directories"
        chown 33:0 "/nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/additional_backup_directories"
        chmod 770 "/nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/additional_backup_directories"
    fi

    # Reset the additional backup directories
    if [ -n "$DAILY_BACKUPTIME" ]; then
        echo "$DAILY_BACKUPTIME" > "/nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/daily_backup_time"
        chown 33:0 "/nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/daily_backup_time"
        chmod 770 "/nextcloud_aio_volumes/nextcloud_aio_mastercontainer/data/daily_backup_time"
    fi

    if [ "$RESTORE_FAILED" = 1 ]; then
        exit 1
    fi

    # Inform user
    get_expiration_time
    echo "Restore finished successfully on $END_DATE_READABLE ($DURATION_READABLE)."

    # Add file to Nextcloud container so that it skips any update the next time
    touch "/nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/skip.update"
    chmod 777 "/nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/skip.update"

    # Add file to Nextcloud container so that it performs a fingerprint update the next time
    touch "/nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/fingerprint.update"
    chmod 777 "/nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/fingerprint.update"

    # Add file to Netcloud container to trigger a preview scan the next time it starts
    if [ -n "$RESTORE_EXCLUDE_PREVIEWS" ]; then
        touch "/nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/trigger-preview.scan"
        chmod 777 "/nextcloud_aio_volumes/nextcloud_aio_nextcloud_data/trigger-preview.scan"
    fi

    # Delete redis cache
    rm -f "/mnt/redis/dump.rdb"
fi

# Do the Backup check
if [ "$BORG_MODE" = check ]; then
    get_start_time
    echo "Checking the backup integrity..."

    # Perform the check
    if ! borg check -v --verify-data; then
        echo "Some errors were found while checking the backup integrity!"
        echo "Check the AIO interface for advice on how to proceed now!"
        exit 1
    fi

    # Inform user
    get_expiration_time
    echo "Check finished successfully on $END_DATE_READABLE ($DURATION_READABLE)."
    exit 0
fi

# Do the Backup check-repair
if [ "$BORG_MODE" = "check-repair" ]; then
    get_start_time
    echo "Checking the backup integrity and repairing it..."

    # Perform the check-repair
    if ! echo YES | borg check -v --repair; then
        echo "Some errors were found while checking and repairing the backup integrity!"
        exit 1
    fi

    # Inform user
    get_expiration_time
    echo "Check finished successfully on $END_DATE_READABLE ($DURATION_READABLE)."
    exit 0
fi

# Do the backup test
if [ "$BORG_MODE" = test ]; then
    if [ -n "$BORG_REMOTE_REPO" ]; then
        if ! borg info > /dev/null; then
            echo "Borg could not get info from the remote repo."
            echo "See the above borg info output for details."
            exit 1
        fi
    else
        if ! [ -d "$BORG_BACKUP_DIRECTORY" ]; then
            echo "No 'borg' directory in the given backup directory found!"
            echo "Only the files/folders below have been found in the given directory."
            ls -a "$MOUNT_DIR"
            echo "Please adjust the directory so that the borg archive is positioned in a folder named 'borg' inside the given directory!"
            exit 1
        elif ! [ -f "$BORG_BACKUP_DIRECTORY/config" ]; then
            echo "A 'borg' directory was found but could not find the borg archive."
            echo "Only the files/folders below have been found in the borg directory."
            ls -a "$BORG_BACKUP_DIRECTORY"
            echo "The archive and most importantly the config file must be positioned directly in the 'borg' subfolder."
            exit 1
        fi
    fi

    if ! borg list >/dev/null; then
        echo "The entered path seems to be valid but could not open the backup archive."
        echo "Most likely the entered password was wrong so please adjust it accordingly!"
        exit 1
    else
        if ! borg list | grep "nextcloud-aio"; then
            echo "The backup archive does not contain a valid Nextcloud AIO backup."
            echo "Most likely was the archive not created via Nextcloud AIO."
            exit 1
        else
            echo "Everything looks fine so feel free to continue!"
            exit 0
        fi
    fi
fi
