#!/bin/bash

# version_greater A B returns whether A > B
version_greater() {
    [ "$(printf '%s\n' "$@" | sort -t '.' -n -k1,1 -k2,2 -k3,3 -k4,4 | head -n 1)" != "$1" ]
}

# return true if specified directory is empty
directory_empty() {
    [ -z "$(ls -A "$1/")" ]
}

run_upgrade_if_needed_due_to_app_update() {
    if php /var/www/html/occ status | grep maintenance | grep -q true; then
        php /var/www/html/occ maintenance:mode --off
    fi
    if php /var/www/html/occ status | grep needsDbUpgrade | grep -q true; then
        php /var/www/html/occ upgrade
        php /var/www/html/occ app:enable nextcloud-aio --force
    fi
}

# Adjust DATABASE_TYPE to by Nextcloud supported value
if [ "$DATABASE_TYPE" = postgres ]; then
    export DATABASE_TYPE=pgsql
fi

# Only start container if Redis is accessible
# shellcheck disable=SC2153
while ! nc -z "$REDIS_HOST" "6379"; do
    echo "Waiting for Redis to start..."
    sleep 5
done

# Check permissions in ncdata
test_file="$NEXTCLOUD_DATA_DIR/this-is-a-test-file"
touch "$test_file"
if ! [ -f "$test_file" ]; then
    echo "The www-data user does not appear to have access rights to the data directory."
    echo "It is possible that the files are on a filesystem that does not support standard Linux permissions,"
    echo "or the permissions simply need to be adjusted. Please change the permissions as described below."
    echo "Current permissions are:"
    stat -c "%u:%g %a" "$NEXTCLOUD_DATA_DIR"
    echo "(userID:groupID permissions)"
    echo "They should be:"
    echo "33:0 750"
    echo "(userID:groupID permissions)"
    echo "Also, ensure that all parent directories on the host of your chosen data directory are publicly readable."
    echo "For example: sudo chmod +r /mnt  (adjust this command as needed)."
    echo "If you want to use a FUSE mount as the data directory, add 'allow_other' as an additional mount option."
    echo "For SMB/CIFS mounts as the data directory, see:"
    echo "  https://github.com/nextcloud/all-in-one#can-i-use-a-cifssmb-share-as-nextclouds-datadir"
    exit 1
fi
rm -f "$test_file"

if [ -f /var/www/html/version.php ]; then
    # shellcheck disable=SC2016
    installed_version="$(php -r 'require "/var/www/html/version.php"; echo implode(".", $OC_Version);')"
else
    installed_version="0.0.0.0"
fi
if [ -f "$SOURCE_LOCATION/version.php" ]; then
    # shellcheck disable=SC2016
    image_version="$(php -r "require '$SOURCE_LOCATION/version.php'; echo implode('.', \$OC_Version);")"
else
    image_version="$installed_version"
fi

# unset admin password
if [ "$installed_version" != "0.0.0.0" ]; then
    unset ADMIN_PASSWORD
fi

# Don't start the container if Nextcloud is not compatible with the PHP version
if [ -f "/var/www/html/lib/versioncheck.php" ] && ! php /var/www/html/lib/versioncheck.php; then
    echo "Your installed Nextcloud version is not compatible with the PHP version provided by this image."
    echo "This typically occurs when you restore an older Nextcloud backup that does not support the"
    echo "PHP version included in this image."
    echo "Please restore a more recent backup that includes a compatible Nextcloud version."
    echo "If you do not have a more recent backup, refer to the manual upgrade documentation:"
    echo "  https://github.com/nextcloud/all-in-one/blob/main/manual-upgrade.md"
    exit 1
fi

# Do not start the container if the last update failed
if [ -f "$NEXTCLOUD_DATA_DIR/update.failed" ]; then
    echo "The last Nextcloud update failed."
    echo "Please restore from a backup and try again."
    echo "If you do not have a backup, you can delete the update.failed file in the data directory"
    echo "to allow the container to start again."
    exit 1
fi

# Do not start the container if the install failed
if [ -f "$NEXTCLOUD_DATA_DIR/install.failed" ]; then
    echo "The initial Nextcloud installation failed."
    echo "For more information about what went wrong, check the logs above."
    echo "Please reset AIO properly and try again."
    echo "See:"
    echo "  https://github.com/nextcloud/all-in-one#how-to-properly-reset-the-instance"
    exit 1
fi

# Skip any update if Nextcloud was just restored
if ! [ -f "$NEXTCLOUD_DATA_DIR/skip.update" ]; then
    if version_greater "$image_version" "$installed_version"; then
        # Check if it skips a major version
        INSTALLED_MAJOR="${installed_version%%.*}"
        IMAGE_MAJOR="${image_version%%.*}"
        
        if [ "$installed_version" != "0.0.0.0" ]; then
            # Write output to logfile.
            exec > >(tee -i "/var/www/html/data/update.log")
            exec 2>&1
        fi

        if [ "$installed_version" != "0.0.0.0" ] && [ "$((IMAGE_MAJOR - INSTALLED_MAJOR))" -gt 1 ]; then
# Do not skip major versions placeholder # Do not remove or change this line!
# Do not skip major versions start # Do not remove or change this line!
            set -ex
            NEXT_MAJOR="$((INSTALLED_MAJOR + 1))"
            curl -fsSL -o nextcloud.tar.bz2 "https://download.nextcloud.com/server/releases/latest-${NEXT_MAJOR}.tar.bz2"
            curl -fsSL -o nextcloud.tar.bz2.asc "https://download.nextcloud.com/server/releases/latest-${NEXT_MAJOR}.tar.bz2.asc"
            GNUPGHOME="$(mktemp -d)"
            export GNUPGHOME
            # gpg key from https://nextcloud.com/nextcloud.asc
            gpg --batch --keyserver keyserver.ubuntu.com --recv-keys 28806A878AE423A28372792ED75899B9A724937A
            gpg --batch --verify nextcloud.tar.bz2.asc nextcloud.tar.bz2
            mkdir -p /usr/src/tmp
            tar -xjf nextcloud.tar.bz2 -C /usr/src/tmp/
            gpgconf --kill all
            rm nextcloud.tar.bz2.asc nextcloud.tar.bz2
            mkdir -p /usr/src/tmp/nextcloud/data
            mkdir -p /usr/src/tmp/nextcloud/custom_apps
            chmod +x /usr/src/tmp/nextcloud/occ
            cp -r "$SOURCE_LOCATION"/config/* /usr/src/tmp/nextcloud/config/
            mkdir -p /usr/src/tmp/nextcloud/apps/nextcloud-aio
            cp -r "$SOURCE_LOCATION"/apps/nextcloud-aio/* /usr/src/tmp/nextcloud/apps/nextcloud-aio/
            mv "$SOURCE_LOCATION" /usr/src/temp-nextcloud
            mv /usr/src/tmp/nextcloud "$SOURCE_LOCATION"
            rm -r /usr/src/tmp
            rm -r /usr/src/temp-nextcloud
            # shellcheck disable=SC2016
            image_version="$(php -r "require '$SOURCE_LOCATION/version.php'; echo implode('.', \$OC_Version);")"
            IMAGE_MAJOR="${image_version%%.*}"
            set +ex
# Do not skip major versions end # Do not remove or change this line!
        fi

        if [ "$installed_version" != "0.0.0.0" ]; then
# Check connection to appstore start # Do not remove or change this line!
            while true; do
                echo -e "Checking connection to the app store..."
                APPSTORE_URL="https://apps.nextcloud.com/api/v1"
                if grep -q appstoreurl /var/www/html/config/config.php; then
                    set -x
                    APPSTORE_URL="$(grep appstoreurl /var/www/html/config/config.php | grep -oP 'https://.*v[0-9]+')"
                    set +x
                fi
                # Default appstoreurl parameter in config.php defaults to 'https://apps.nextcloud.com/api/v1' so we check for the apps.json file stored in there
                CURL_STATUS="$(curl -LI "$APPSTORE_URL"/apps.json -o /dev/null -w '%{http_code}\n' -s)"
                if [[ "$CURL_STATUS" = "200" ]]
                then
                    echo "App store is reachable."
                    break
                else
                    echo "Curl did not return a 200 status. Is the app store reachable?"
                    sleep 5
                fi
            done
# Check connection to appstore end # Do not remove or change this line!

            run_upgrade_if_needed_due_to_app_update

            php /var/www/html/occ maintenance:mode --off

            echo "Getting and backing up the status of apps for later; this might take a while..."
            NC_APPS="$(find /var/www/html/custom_apps/ -type d -maxdepth 1 -mindepth 1 | sed 's|/var/www/html/custom_apps/||g')"
            if [ -z "$NC_APPS" ]; then
                echo "No apps detected. Aborting export of app status..."
                APPSTORAGE="no-export-done"
            else
                mapfile -t NC_APPS_ARRAY <<< "$NC_APPS"
                declare -Ag APPSTORAGE
                echo "Disabling apps before the update to make the update procedure safer. This can take a while..."
                for app in "${NC_APPS_ARRAY[@]}"; do
                    if APPSTORAGE[$app]="$(php /var/www/html/occ config:app:get "$app" enabled)"; then
                        php /var/www/html/occ app:disable "$app"
                    else
                        APPSTORAGE[$app]=""
                        echo "Not disabling $app because the occ command to get its enabled state failed."
                    fi
                done
            fi

            if [ "$((IMAGE_MAJOR - INSTALLED_MAJOR))" -eq 1 ]; then
                php /var/www/html/occ config:system:delete app_install_overwrite
            fi

            php /var/www/html/occ app:update --all

            run_upgrade_if_needed_due_to_app_update
        fi

        echo "Initializing Nextcloud $image_version ..."

        # Copy over initial data from Nextcloud archive
        rsync -rlD --delete \
            --exclude-from=/upgrade.exclude \
            "$SOURCE_LOCATION/" \
            /var/www/html/

        # Copy custom_apps from Nextcloud archive
        if ! directory_empty "$SOURCE_LOCATION/custom_apps"; then
            set -x
            for app in "$SOURCE_LOCATION/custom_apps"/*; do
                app_id="$(basename "$app")"
                mkdir -p "/var/www/html/custom_apps/$app_id"
                rsync -rlD --delete \
                    --include "/$app_id/" \
                    --exclude '/*' \
                    "$SOURCE_LOCATION/custom_apps/" \
                    /var/www/html/custom_apps/
            done
            set +x
        fi

        # Copy these from Nextcloud archive if they don't exist yet (i.e. new install)
        for dir in config data custom_apps themes; do
            if [ ! -d "/var/www/html/$dir" ] || directory_empty "/var/www/html/$dir"; then
                rsync -rlD \
                    --include "/$dir/" \
                    --exclude '/*' \
                    "$SOURCE_LOCATION/" \
                    /var/www/html/
            fi
        done

        rsync -rlD --delete \
            --include '/config/' \
            --exclude '/*' \
            --exclude '/config/CAN_INSTALL' \
            --exclude '/config/config.sample.php' \
            --exclude '/config/config.php' \
            "$SOURCE_LOCATION/" \
            /var/www/html/

        rsync -rlD \
            --include '/version.php' \
            --exclude '/*' \
            "$SOURCE_LOCATION/" \
            /var/www/html/

        echo "Initializing finished"

        ################
        # Fresh Install
        ################
        
        if [ "$installed_version" = "0.0.0.0" ]; then
            echo "New Nextcloud instance."

            # Write output to logfile.
            mkdir -p /var/www/html/data
            exec > >(tee -i "/var/www/html/data/install.log")
            exec 2>&1

            INSTALL_OPTIONS=(-n --admin-user "$ADMIN_USER" --admin-pass "$ADMIN_PASSWORD")
            if [ -n "${NEXTCLOUD_DATA_DIR}" ]; then
                INSTALL_OPTIONS+=(--data-dir "$NEXTCLOUD_DATA_DIR")
            fi

        # Skip the default permission check (we do our own)
        cat > /var/www/html/config/datadir.permission.config.php <<'EOF'
<?php
    $CONFIG = array (
        'check_data_directory_permissions' => false
    );
EOF

            # Write out postgres root cert
            if [ -n "$NEXTCLOUD_TRUSTED_CERTIFICATES_POSTGRES" ]; then
                mkdir /var/www/html/data/certificates
                echo "$NEXTCLOUD_TRUSTED_CERTIFICATES_POSTGRES" > "/var/www/html/data/certificates/POSTGRES"
            # Write out mysql root cert
            elif [ -n "$NEXTCLOUD_TRUSTED_CERTIFICATES_MYSQL" ]; then
                mkdir /var/www/html/data/certificates
                echo "$NEXTCLOUD_TRUSTED_CERTIFICATES_MYSQL" > "/var/www/html/data/certificates/MYSQL"
            fi

            echo "Installing with $DATABASE_TYPE database"
            # Set a default value for POSTGRES_PORT
            if [ -z "$POSTGRES_PORT" ]; then
                POSTGRES_PORT=5432
            fi

            # Add database options to INSTALL_OPTIONS
            # shellcheck disable=SC2153
            INSTALL_OPTIONS+=(
                --database "$DATABASE_TYPE"
                --database-name "$POSTGRES_DB"
                --database-user "$POSTGRES_USER"
                --database-pass "$POSTGRES_PASSWORD"
                --database-host "$POSTGRES_HOST"
                --database-port "$POSTGRES_PORT"
            )
            
            echo "Starting Nextcloud installation..."
            if ! php /var/www/html/occ maintenance:install "${INSTALL_OPTIONS[@]}"; then
                echo "Installation of Nextcloud failed!"
                touch "$NEXTCLOUD_DATA_DIR/install.failed"
                exit 1
            fi

            # Try to force generation of appdata dir:
            php /var/www/html/occ maintenance:repair

            if [ -z "$OBJECTSTORE_S3_BUCKET" ] && [ -z "$OBJECTSTORE_SWIFT_URL" ]; then
                max_retries=10
                try=0
                while [ -z "$(find "$NEXTCLOUD_DATA_DIR/" -maxdepth 1 -mindepth 1 -type d -name "appdata_*")" ] && [ "$try" -lt "$max_retries" ]; do
                    echo "Waiting for appdata to become available..."
                    try=$((try+1))
                    sleep 10s
                done

                if [ "$try" -ge "$max_retries" ]; then
                    echo "Installation of Nextcloud failed!"
                    echo "Installation errors: $(cat /var/www/html/data/nextcloud.log)"
                    touch "$NEXTCLOUD_DATA_DIR/install.failed"
                    exit 1
                fi
            fi

            # This autoconfig is not needed anymore and should be able to be overwritten by the user
            rm /var/www/html/config/datadir.permission.config.php

            # unset admin password
            unset ADMIN_PASSWORD

            # Enable the updatenotification app but disable its UI and server update notifications
            php /var/www/html/occ config:system:set updatechecker --type=bool --value=false
            php /var/www/html/occ config:app:set updatenotification notify_groups --value="[]"

# AIO update to latest start # Do not remove or change this line!
            if [ "$INSTALL_LATEST_MAJOR" = yes ]; then
                php /var/www/html/occ config:system:set updatedirectory --value="/nc-updater"
                INSTALLED_AT="$(php /var/www/html/occ config:app:get core installedat)"
                if [ -n "${INSTALLED_AT}" ]; then
                    # Set the installdat to 00 which will allow to skip staging and install the next major directly
                    # shellcheck disable=SC2001
                    INSTALLED_AT="$(echo "${INSTALLED_AT}" | sed "s|[0-9][0-9]$|00|")"
                    php /var/www/html/occ config:app:set core installedat --value="${INSTALLED_AT}" 
                fi
                php /var/www/html/updater/updater.phar --no-interaction --no-backup
                if ! php /var/www/html/occ -V || php /var/www/html/occ status | grep maintenance | grep -q 'true'; then
                    echo "Installation of Nextcloud failed!"
                    touch "$NEXTCLOUD_DATA_DIR/install.failed"
                    exit 1
                fi
                # shellcheck disable=SC2016
                installed_version="$(php -r 'require "/var/www/html/version.php"; echo implode(".", $OC_Version);')"
                INSTALLED_MAJOR="${installed_version%%.*}"
                IMAGE_MAJOR="${image_version%%.*}"
                # If a valid upgrade path, trigger the Nextcloud built-in Updater
                if ! [ "$INSTALLED_MAJOR" -gt "$IMAGE_MAJOR" ]; then
                    php /var/www/html/updater/updater.phar --no-interaction --no-backup
                    if ! php /var/www/html/occ -V || php /var/www/html/occ status | grep maintenance | grep -q 'true'; then
                        echo "Installation of Nextcloud failed!"
                        # TODO: Add a hint here about what to do / where to look / updater.log? 
                        touch "$NEXTCLOUD_DATA_DIR/install.failed"
                        exit 1
                    fi
                    # shellcheck disable=SC2016
                    installed_version="$(php -r 'require "/var/www/html/version.php"; echo implode(".", $OC_Version);')"
                fi
                php /var/www/html/occ config:system:set updatechecker --type=bool --value=true
                php /var/www/html/occ app:enable nextcloud-aio --force
                php /var/www/html/occ db:add-missing-columns
                php /var/www/html/occ db:add-missing-primary-keys
                yes | php /var/www/html/occ db:convert-filecache-bigint
            fi
# AIO update to latest end # Do not remove or change this line!

            # Apply log settings
            echo "Applying default settings..."
            mkdir -p /var/www/html/data
            php /var/www/html/occ config:system:set loglevel --value="2" --type=integer
            php /var/www/html/occ config:system:set log_type --value="file"
            php /var/www/html/occ config:system:set logfile --value="/var/www/html/data/nextcloud.log"
            php /var/www/html/occ config:system:set log_rotate_size --value="10485760" --type=integer
            php /var/www/html/occ app:enable admin_audit
            php /var/www/html/occ config:app:set admin_audit logfile --value="/var/www/html/data/audit.log"
            php /var/www/html/occ config:system:set log.condition apps 0 --value="admin_audit"

            # Apply preview settings
            echo "Applying preview settings..."
            php /var/www/html/occ config:system:set preview_max_x --value="2048" --type=integer
            php /var/www/html/occ config:system:set preview_max_y --value="2048" --type=integer
            php /var/www/html/occ config:system:set jpeg_quality --value="60" --type=integer
            php /var/www/html/occ config:app:set preview jpeg_quality --value="60"
            php /var/www/html/occ config:system:delete enabledPreviewProviders
            php /var/www/html/occ config:system:set enabledPreviewProviders 1 --value="OC\\Preview\\Image"
            php /var/www/html/occ config:system:set enabledPreviewProviders 2 --value="OC\\Preview\\MarkDown"
            php /var/www/html/occ config:system:set enabledPreviewProviders 3 --value="OC\\Preview\\MP3"
            php /var/www/html/occ config:system:set enabledPreviewProviders 4 --value="OC\\Preview\\TXT"
            php /var/www/html/occ config:system:set enabledPreviewProviders 5 --value="OC\\Preview\\OpenDocument"
            php /var/www/html/occ config:system:set enabledPreviewProviders 6 --value="OC\\Preview\\Movie"
            php /var/www/html/occ config:system:set enabledPreviewProviders 7 --value="OC\\Preview\\Krita"
            php /var/www/html/occ config:system:set enable_previews --value=true --type=boolean

            # Apply other settings
            echo "Applying other settings..."
            # Add missing indices after new installation because they seem to be missing on new installation
            php /var/www/html/occ db:add-missing-indices
            php /var/www/html/occ config:system:set upgrade.disable-web --type=bool --value=true
            php /var/www/html/occ config:system:set mail_smtpmode --value="smtp"
            php /var/www/html/occ config:system:set trashbin_retention_obligation --value="auto, 30"
            php /var/www/html/occ config:system:set versions_retention_obligation --value="auto, 30"
            php /var/www/html/occ config:system:set activity_expire_days --value="30" --type=integer
            php /var/www/html/occ config:system:set simpleSignUpLink.shown --type=bool --value=false
            php /var/www/html/occ config:system:set share_folder --value="/Shared"

            # Install some apps by default
            if [ -n "$STARTUP_APPS" ]; then
                read -ra STARTUP_APPS_ARRAY <<< "$STARTUP_APPS"
                for app in "${STARTUP_APPS_ARRAY[@]}"; do
                    if ! echo "$app" | grep -q '^-'; then 
                        if [ -z "$(find /var/www/html/apps /var/www/html/custom_apps -type d -maxdepth 1 -mindepth 1 -name "$app" )" ]; then
                            # If not shipped, install and enable the app
                            php /var/www/html/occ app:install "$app"
                        else
                            # If shipped, enable the app
                            php /var/www/html/occ app:enable "$app"
                        fi
                    else
                        app="${app#-}"
                        # Disable the app if '-' was provided in front of the appid
                        php /var/www/html/occ app:disable "$app"
                    fi
                done
            fi

        #upgrade
        else
            touch "$NEXTCLOUD_DATA_DIR/update.failed"
            echo "Upgrading Nextcloud from $installed_version to $image_version..."
            php /var/www/html/occ config:system:delete integrity.check.disabled
            if ! php /var/www/html/occ upgrade || ! php /var/www/html/occ -V; then
                echo "Upgrade failed. Please restore from backup."
                bash /notify.sh "Nextcloud update to $image_version failed!" "Please restore from backup."
                exit 1
            fi

            # shellcheck disable=SC2016
            installed_version="$(php -r 'require "/var/www/html/version.php"; echo implode(".", $OC_Version);')"

            rm "$NEXTCLOUD_DATA_DIR/update.failed"
            bash /notify.sh "Nextcloud update to $image_version successful!" "You may inspect the Nextcloud container logs for more information."

            php /var/www/html/occ app:update --all

            run_upgrade_if_needed_due_to_app_update

            # Restore app status
            if [ "${APPSTORAGE[0]}" != "no-export-done" ]; then
                echo "Restoring app statuses. This may take a while..."
                for app in "${!APPSTORAGE[@]}"; do
                    if [ -n "${APPSTORAGE[$app]}" ]; then
                        if [ "${APPSTORAGE[$app]}" != "no" ]; then
                            echo "Enabling $app..."
                            if ! php /var/www/html/occ app:enable "$app" >/dev/null; then
                                php /var/www/html/occ app:disable "$app" >/dev/null
                                if ! php /var/www/html/occ -V &>/dev/null; then
                                    rm -r "/var/www/html/custom_apps/$app"
                                    php /var/www/html/occ maintenance:mode --off
                                fi
                                run_upgrade_if_needed_due_to_app_update
                                echo "The $app app could not be re-enabled, probably because it is not compatible with the new Nextcloud version."
                                if [ "$app" = apporder ]; then
                                    CUSTOM_HINT="The apporder app was deprecated. A possible replacement is the side_menu app, aka 'Custom menu'."
                                else
                                    CUSTOM_HINT="Most likely, it is not compatible with the new Nextcloud version."
                                fi
                                bash /notify.sh "Could not re-enable the $app app after the Nextcloud update!" "$CUSTOM_HINT Feel free to review the Nextcloud update logs and force-enable the app again if you wish."
                                continue
                            fi
                            # Only restore the group settings, if the app was enabled (and is thus compatible with the new NC version)
                            if [ "${APPSTORAGE[$app]}" != "yes" ]; then
                                php /var/www/html/occ config:app:set "$app" enabled --value="${APPSTORAGE[$app]}"
                            fi
                        fi
                    fi
                done
            fi

            php /var/www/html/occ app:update --all

            run_upgrade_if_needed_due_to_app_update

            # Enable the updatenotification app but disable its UI and server update notifications
            php /var/www/html/occ config:system:set updatechecker --type=bool --value=false
            php /var/www/html/occ app:enable updatenotification
            php /var/www/html/occ config:app:set updatenotification notify_groups --value="[]"

            # Apply optimization
            echo "Performing some optimizations..."
            if [ "$NEXTCLOUD_SKIP_DATABASE_OPTIMIZATION" != yes ]; then
                php /var/www/html/occ maintenance:repair --include-expensive
                php /var/www/html/occ db:add-missing-indices
                php /var/www/html/occ db:add-missing-columns
                php /var/www/html/occ db:add-missing-primary-keys
                yes | php /var/www/html/occ db:convert-filecache-bigint
            else
                php /var/www/html/occ maintenance:repair
            fi
        fi
    fi

    # Performing update of all apps if daily backups are enabled, running and successful and if it is saturday
    if [ "$UPDATE_NEXTCLOUD_APPS" = 'yes' ] && [ "$(date +%u)" = 6 ]; then
        UPDATED_APPS="$(php /var/www/html/occ app:update --all)"
        run_upgrade_if_needed_due_to_app_update
        if [ -n "$UPDATED_APPS" ]; then
             bash /notify.sh "Your apps just got updated!" "$UPDATED_APPS"
        fi
    fi
else
    SKIP_UPDATE=1
fi

run_upgrade_if_needed_due_to_app_update

if [ -z "$OBJECTSTORE_S3_BUCKET" ] && [ -z "$OBJECTSTORE_SWIFT_URL" ]; then
    # Check if appdata is present
    # If not, something broke (e.g. changing ncdatadir after aio was first started)
    if [ -z "$(find "$NEXTCLOUD_DATA_DIR/" -maxdepth 1 -mindepth 1 -type d -name "appdata_*")" ]; then
        echo "Appdata is not present. Did you change the datadir after the initial Nextcloud installation? This is not supported!"
        echo "See https://github.com/nextcloud/all-in-one#how-to-change-the-default-location-of-nextclouds-datadir"
        echo "If you moved the datadir to an external drive, make sure that the drive is still mounted."
        echo "The following was found in the datadir:"
        ls -la "$NEXTCLOUD_DATA_DIR/"
        exit 1
    fi

    # Delete formerly configured tempdirectory as the default is usually faster (if the datadir is on a HDD or network FS)
    if [ "$(php /var/www/html/occ config:system:get tempdirectory)" = "$NEXTCLOUD_DATA_DIR/tmp/" ]; then
        php /var/www/html/occ config:system:delete tempdirectory
        if [ -d "$NEXTCLOUD_DATA_DIR/tmp/" ]; then
            rm -r "$NEXTCLOUD_DATA_DIR/tmp/"
        fi
    fi

fi

# Perform fingerprint update if instance was restored
if [ -f "$NEXTCLOUD_DATA_DIR/fingerprint.update" ]; then
    php /var/www/html/occ maintenance:data-fingerprint
    rm "$NEXTCLOUD_DATA_DIR/fingerprint.update"
fi

# Perform preview scan if previews were excluded from restore
if [ -f "$NEXTCLOUD_DATA_DIR/trigger-preview.scan" ]; then
    php /var/www/html/occ files:scan-app-data preview -vvv
    rm "$NEXTCLOUD_DATA_DIR/trigger-preview.scan"
fi

# AIO one-click settings start # Do not remove or change this line!
# Apply one-click-instance settings
echo "Applying one-click-instance settings..."
php /var/www/html/occ config:system:set one-click-instance --value=true --type=bool
php /var/www/html/occ config:system:set one-click-instance.user-limit --value=100 --type=int
php /var/www/html/occ config:system:set one-click-instance.link --value="https://nextcloud.com/all-in-one/"
# AIO one-click settings end # Do not remove or change this line!
php /var/www/html/occ app:enable support
if [ -n "$SUBSCRIPTION_KEY" ] && [ -z "$(php /var/www/html/occ config:app:get support potential_subscription_key)" ]; then
    php /var/www/html/occ config:app:set support potential_subscription_key --value="$SUBSCRIPTION_KEY"
    php /var/www/html/occ config:app:delete support last_check
fi
if [ -n "$NEXTCLOUD_DEFAULT_QUOTA" ]; then
    if [ "$NEXTCLOUD_DEFAULT_QUOTA" = "unlimited" ]; then
        php /var/www/html/occ config:app:delete files default_quota
    else
        php /var/www/html/occ config:app:set files default_quota --value="$NEXTCLOUD_DEFAULT_QUOTA"
    fi
fi

# Adjusting log files to be stored on a volume
echo "Adjusting log files..."
php /var/www/html/occ config:system:set upgrade.cli-upgrade-link --value="https://github.com/nextcloud/all-in-one/discussions/2726"
php /var/www/html/occ config:system:set logfile --value="/var/www/html/data/nextcloud.log"
php /var/www/html/occ config:app:set admin_audit logfile --value="/var/www/html/data/audit.log"
php /var/www/html/occ config:system:set updatedirectory --value="/nc-updater"
if [ -n "$NEXTCLOUD_SKELETON_DIRECTORY" ]; then
    if [ "$NEXTCLOUD_SKELETON_DIRECTORY" = "empty" ]; then
        php /var/www/html/occ config:system:set skeletondirectory --value=""
    else
        php /var/www/html/occ config:system:set skeletondirectory --value="$NEXTCLOUD_SKELETON_DIRECTORY"
    fi
fi
if [ -n "$SERVERINFO_TOKEN" ] && [ -z "$(php /var/www/html/occ config:app:get serverinfo token)" ]; then
    php /var/www/html/occ config:app:set serverinfo token --value="$SERVERINFO_TOKEN"
fi
# Set maintenance window so that no warning is shown in the admin overview
if [ -z "$NEXTCLOUD_MAINTENANCE_WINDOW" ]; then
    NEXTCLOUD_MAINTENANCE_WINDOW=100
fi
php /var/www/html/occ config:system:set maintenance_window_start --type=int --value="$NEXTCLOUD_MAINTENANCE_WINDOW"

# Apply network settings
echo "Applying network settings..."
php /var/www/html/occ config:system:set allow_local_remote_servers --type=bool --value=true
php /var/www/html/occ config:system:set davstorage.request_timeout --value="$PHP_MAX_TIME" --type=int
php /var/www/html/occ config:system:set trusted_domains 1 --value="$NC_DOMAIN"
php /var/www/html/occ config:system:set overwrite.cli.url --value="https://$NC_DOMAIN/"
php /var/www/html/occ config:system:set documentation_url.server_logs --value="https://github.com/nextcloud/all-in-one/discussions/5425"
php /var/www/html/occ config:system:set htaccess.RewriteBase --value="/"
php /var/www/html/occ maintenance:update:htaccess

# Revert dbpersistent setting to check if it fixes too many db connections
php /var/www/html/occ config:system:set dbpersistent --value=false --type=bool

if [ "$DISABLE_BRUTEFORCE_PROTECTION" = yes ]; then
    php /var/www/html/occ config:system:set auth.bruteforce.protection.enabled --type=bool --value=false
    php /var/www/html/occ config:system:set ratelimit.protection.enabled --type=bool --value=false
else
    php /var/www/html/occ config:system:set auth.bruteforce.protection.enabled --type=bool --value=true
    php /var/www/html/occ config:system:set ratelimit.protection.enabled --type=bool --value=true
fi

# Disallow creating local external storages when nothing was mounted
if [ -z "$NEXTCLOUD_MOUNT" ]; then
    php /var/www/html/occ config:system:set files_external_allow_create_new_local --type=bool --value=false
else
    php /var/www/html/occ config:system:set files_external_allow_create_new_local --type=bool --value=true
fi

# AIO app start # Do not remove or change this line!
# AIO app
if [ "$THIS_IS_AIO" = "true" ]; then
    if [ "$(php /var/www/html/occ config:app:get nextcloud-aio enabled)" != "yes" ]; then
        php /var/www/html/occ app:enable nextcloud-aio
    fi
else
    if [ "$(php /var/www/html/occ config:app:get nextcloud-aio enabled)" != "no" ]; then
        php /var/www/html/occ app:disable nextcloud-aio
    fi
fi
# AIO app end # Do not remove or change this line!

# Allow to add custom certs to Nextcloud's trusted cert store
if env | grep -q NEXTCLOUD_TRUSTED_CERTIFICATES_; then
    set -x
    TRUSTED_CERTIFICATES="$(env | grep NEXTCLOUD_TRUSTED_CERTIFICATES_ | grep -oP '^[A-Z_a-z0-9]+')"
    mapfile -t TRUSTED_CERTIFICATES <<< "$TRUSTED_CERTIFICATES"
    CERTIFICATES_ROOT_DIR="/var/www/html/data/certificates"
    mkdir -p "$CERTIFICATES_ROOT_DIR"
    for certificate in "${TRUSTED_CERTIFICATES[@]}"; do
        # shellcheck disable=SC2001
        CERTIFICATE_NAME="$(echo "$certificate" | sed 's|^NEXTCLOUD_TRUSTED_CERTIFICATES_||')"
        if ! [ -f "$CERTIFICATES_ROOT_DIR/$CERTIFICATE_NAME" ]; then
            echo "${!certificate}" > "$CERTIFICATES_ROOT_DIR/$CERTIFICATE_NAME"
            php /var/www/html/occ security:certificates:import "$CERTIFICATES_ROOT_DIR/$CERTIFICATE_NAME"
        fi
    done
    set +x
fi

# Notify push
if ! [ -d "/var/www/html/custom_apps/notify_push" ]; then
    php /var/www/html/occ app:install notify_push
elif [ "$(php /var/www/html/occ config:app:get notify_push enabled)" != "yes" ]; then
    php /var/www/html/occ app:enable notify_push
elif [ "$SKIP_UPDATE" != 1 ]; then
    php /var/www/html/occ app:update notify_push
fi
chmod 775 -R /var/www/html/custom_apps/notify_push/bin/
php /var/www/html/occ config:system:set trusted_proxies 0 --value="127.0.0.1"
php /var/www/html/occ config:system:set trusted_proxies 1 --value="::1"
if [ -n "$ADDITIONAL_TRUSTED_PROXY" ]; then
    php /var/www/html/occ config:system:set trusted_proxies 2 --value="$ADDITIONAL_TRUSTED_PROXY"
fi

# Get ipv4-address of Nextcloud
if [ -z "$NEXTCLOUD_HOST" ]; then
    export NEXTCLOUD_HOST="nextcloud-aio-nextcloud"
fi
IPv4_ADDRESS="$(dig "$NEXTCLOUD_HOST" A +short +search | head -1)" 
# Bring it in CIDR notation 
# shellcheck disable=SC2001
IPv4_ADDRESS="$(echo "$IPv4_ADDRESS" | sed 's|[0-9]\+$|0/16|')" 
if [ -n "$IPv4_ADDRESS" ]; then
    php /var/www/html/occ config:system:set trusted_proxies 10 --value="$IPv4_ADDRESS"
fi

if [ -n "$ADDITIONAL_TRUSTED_DOMAIN" ]; then
    php /var/www/html/occ config:system:set trusted_domains 2 --value="$ADDITIONAL_TRUSTED_DOMAIN"
fi
php /var/www/html/occ config:app:set notify_push base_endpoint --value="https://$NC_DOMAIN/push"

# Collabora
if [ "$COLLABORA_ENABLED" = 'yes' ]; then
    set -x
    if echo "$COLLABORA_HOST" | grep -q "nextcloud-.*-collabora"; then
        COLLABORA_HOST="$NC_DOMAIN"
    fi
    set +x
    # Remove richdcoumentscode if it should be incorrectly installed
    if [ -d "/var/www/html/custom_apps/richdocumentscode" ]; then
        php /var/www/html/occ app:remove richdocumentscode
    fi
    if ! [ -d "/var/www/html/custom_apps/richdocuments" ]; then
        php /var/www/html/occ app:install richdocuments
    elif [ "$(php /var/www/html/occ config:app:get richdocuments enabled)" != "yes" ]; then
        php /var/www/html/occ app:enable richdocuments
    elif [ "$SKIP_UPDATE" != 1 ]; then
        php /var/www/html/occ app:update richdocuments
    fi
    php /var/www/html/occ config:app:set richdocuments wopi_url --value="https://$COLLABORA_HOST/"
    # Make collabora more save
    COLLABORA_IPv4_ADDRESS="$(dig "$COLLABORA_HOST" A +short +search | grep '^[0-9.]\+$' | sort | head -n1)"
    COLLABORA_IPv6_ADDRESS="$(dig "$COLLABORA_HOST" AAAA +short +search | grep '^[0-9a-f:]\+$' | sort | head -n1)"
    COLLABORA_ALLOW_LIST="$(php /var/www/html/occ config:app:get richdocuments wopi_allowlist)"
    if [ -n "$COLLABORA_IPv4_ADDRESS" ]; then
        if ! echo "$COLLABORA_ALLOW_LIST" | grep -q "$COLLABORA_IPv4_ADDRESS"; then
            if [ -z "$COLLABORA_ALLOW_LIST" ]; then
                COLLABORA_ALLOW_LIST="$COLLABORA_IPv4_ADDRESS"
            else
                COLLABORA_ALLOW_LIST+=",$COLLABORA_IPv4_ADDRESS"
            fi
        fi
    else
        echo "Warning: No IPv4 address found for $COLLABORA_HOST."
    fi
    if [ -n "$COLLABORA_IPv6_ADDRESS" ]; then
        if ! echo "$COLLABORA_ALLOW_LIST" | grep -q "$COLLABORA_IPv6_ADDRESS"; then
            if [ -z "$COLLABORA_ALLOW_LIST" ]; then
                COLLABORA_ALLOW_LIST="$COLLABORA_IPv6_ADDRESS"
            else
                COLLABORA_ALLOW_LIST+=",$COLLABORA_IPv6_ADDRESS"
            fi
        fi
    else
        echo "No IPv6 address found for $COLLABORA_HOST."
    fi
    if [ -n "$COLLABORA_ALLOW_LIST" ]; then
        PRIVATE_IP_RANGES='127.0.0.0/8,192.168.0.0/16,172.16.0.0/12,10.0.0.0/8,100.64.0.0/10,fd00::/8,::1/128'
        if ! echo "$COLLABORA_ALLOW_LIST" | grep -q "$PRIVATE_IP_RANGES"; then
            COLLABORA_ALLOW_LIST+=",$PRIVATE_IP_RANGES"
        fi
        if [ -n "$ADDITIONAL_TRUSTED_PROXY" ]; then
            if ! echo "$COLLABORA_ALLOW_LIST" | grep -q "$ADDITIONAL_TRUSTED_PROXY"; then
                COLLABORA_ALLOW_LIST+=",$ADDITIONAL_TRUSTED_PROXY"
            fi
        fi
        php /var/www/html/occ config:app:set richdocuments wopi_allowlist --value="$COLLABORA_ALLOW_LIST"
    else
        echo "Warning: wopi_allowlist is empty; this should not be the case!"
    fi
else
    if [ "$REMOVE_DISABLED_APPS" = yes ] && [ -d "/var/www/html/custom_apps/richdocuments" ]; then
        php /var/www/html/occ app:remove richdocuments
    fi
fi

# OnlyOffice
if [ "$ONLYOFFICE_ENABLED" = 'yes' ]; then
    # Determine OnlyOffice port based on host pattern
    if echo "$ONLYOFFICE_HOST" | grep -q "nextcloud-.*-onlyoffice"; then
        ONLYOFFICE_PORT=80
    else
        ONLYOFFICE_PORT=443
    fi

    count=0
    while ! nc -z "$ONLYOFFICE_HOST" "$ONLYOFFICE_PORT" && [ "$count" -lt 90 ]; do
        echo "Waiting for OnlyOffice to become available..."
        count=$((count+5))
        sleep 5
    done
    if [ "$count" -ge 90 ]; then
        bash /notify.sh "Onlyoffice did not start in time!" "Skipping initialization and disabling onlyoffice app."
        php /var/www/html/occ app:disable onlyoffice
    else
        # Install or enable OnlyOffice app as needed
        if ! [ -d "/var/www/html/custom_apps/onlyoffice" ]; then
            php /var/www/html/occ app:install onlyoffice
        elif [ "$(php /var/www/html/occ config:app:get onlyoffice enabled)" != "yes" ]; then
            php /var/www/html/occ app:enable onlyoffice
        elif [ "$SKIP_UPDATE" != 1 ]; then
            php /var/www/html/occ app:update onlyoffice
        fi

        # Set OnlyOffice configuration
        php /var/www/html/occ config:system:set onlyoffice jwt_secret --value="$ONLYOFFICE_SECRET"
        php /var/www/html/occ config:app:set onlyoffice jwt_secret --value="$ONLYOFFICE_SECRET"
        php /var/www/html/occ config:system:set onlyoffice jwt_header --value="AuthorizationJwt"

        # Adjust the OnlyOffice host if using internal pattern
        if echo "$ONLYOFFICE_HOST" | grep -q "nextcloud-.*-onlyoffice"; then
            ONLYOFFICE_HOST="$NC_DOMAIN/onlyoffice"
            export ONLYOFFICE_HOST
        fi

        php /var/www/html/occ config:app:set onlyoffice DocumentServerUrl --value="https://$ONLYOFFICE_HOST"
    fi
else
    # Remove OnlyOffice app if disabled and removal is requested
    if [ "$REMOVE_DISABLED_APPS" = yes ] && \
       [ -d "/var/www/html/custom_apps/onlyoffice" ] && \
       [ -n "$ONLYOFFICE_SECRET" ] && \
       [ "$(php /var/www/html/occ config:system:get onlyoffice jwt_secret)" = "$ONLYOFFICE_SECRET" ]; then
        php /var/www/html/occ app:remove onlyoffice
    fi
fi

# Talk
if [ "$TALK_ENABLED" = 'yes' ]; then
    set -x
    if [ -z "$TALK_HOST" ] || echo "$TALK_HOST" | grep -q "nextcloud-.*-talk"; then
        TALK_HOST="$NC_DOMAIN"
        HPB_PATH="/standalone-signaling/"
    fi
    if [ -z "$TURN_DOMAIN" ]; then
        TURN_DOMAIN="$TALK_HOST"
    fi
    set +x
    if ! [ -d "/var/www/html/custom_apps/spreed" ]; then
        php /var/www/html/occ app:install spreed
    elif [ "$(php /var/www/html/occ config:app:get spreed enabled)" != "yes" ]; then
        php /var/www/html/occ app:enable spreed
    elif [ "$SKIP_UPDATE" != 1 ]; then
        php /var/www/html/occ app:update spreed
    fi
    # Based on https://github.com/nextcloud/spreed/issues/960#issuecomment-416993435
    if [ -z "$(php /var/www/html/occ talk:turn:list --output="plain")" ]; then
        # shellcheck disable=SC2153
        php /var/www/html/occ talk:turn:add turn "$TURN_DOMAIN:$TALK_PORT" "udp,tcp" --secret="$TURN_SECRET"
    fi
    STUN_SERVER="$(php /var/www/html/occ talk:stun:list --output="plain")"
    if [ -z "$STUN_SERVER" ] || echo "$STUN_SERVER" | grep -oP '[a-zA-Z.:0-9]+' | grep -q "^stun.nextcloud.com:443$"; then
        php /var/www/html/occ talk:stun:add "$TURN_DOMAIN:$TALK_PORT"
        php /var/www/html/occ talk:stun:delete "stun.nextcloud.com:443"
    fi
    if ! php /var/www/html/occ talk:signaling:list --output="plain" | grep -q "https://$TALK_HOST$HPB_PATH"; then
        php /var/www/html/occ talk:signaling:add "https://$TALK_HOST$HPB_PATH" "$SIGNALING_SECRET" --verify
    fi
else
    if [ "$REMOVE_DISABLED_APPS" = yes ] && [ -d "/var/www/html/custom_apps/spreed" ]; then
        php /var/www/html/occ app:remove spreed
    fi
fi

# Talk recording
if [ -d "/var/www/html/custom_apps/spreed" ]; then
    if [ "$TALK_RECORDING_ENABLED" = 'yes' ]; then
        while ! nc -z "$TALK_RECORDING_HOST" 1234; do
            echo "Waiting for Talk Recording to become available..."
            sleep 5
        done
        # TODO: migrate to occ command if that becomes available
        RECORDING_SERVERS_STRING="{\"servers\":[{\"server\":\"http://$TALK_RECORDING_HOST:1234/\",\"verify\":true}],\"secret\":\"$RECORDING_SECRET\"}"
        php /var/www/html/occ config:app:set spreed recording_servers --value="$RECORDING_SERVERS_STRING"
    else
        php /var/www/html/occ config:app:delete spreed recording_servers
    fi
fi

# Clamav
if [ "$CLAMAV_ENABLED" = 'yes' ]; then
    count=0
    while ! nc -z "$CLAMAV_HOST" 3310 && [ "$count" -lt 90 ]; do
        echo "Waiting for ClamAV to become available..."
        count=$((count+5))
        sleep 5
    done
    if [ "$count" -ge 90 ]; then
        bash /notify.sh "ClamAV did not start in time!" "Skipping initialization and disabling files_antivirus app."
        php /var/www/html/occ app:disable files_antivirus
    else
        if ! [ -d "/var/www/html/custom_apps/files_antivirus" ]; then
            php /var/www/html/occ app:install files_antivirus
        elif [ "$(php /var/www/html/occ config:app:get files_antivirus enabled)" != "yes" ]; then
            php /var/www/html/occ app:enable files_antivirus
        elif [ "$SKIP_UPDATE" != 1 ]; then
            php /var/www/html/occ app:update files_antivirus
        fi
        php /var/www/html/occ config:app:set files_antivirus av_mode --value="daemon"
        php /var/www/html/occ config:app:set files_antivirus av_port --value="3310"
        php /var/www/html/occ config:app:set files_antivirus av_host --value="$CLAMAV_HOST"
        # av_stream_max_length must be synced with StreamMaxLength inside clamav
        php /var/www/html/occ config:app:set files_antivirus av_stream_max_length --value="2147483648"
        php /var/www/html/occ config:app:set files_antivirus av_max_file_size --value="-1"
        php /var/www/html/occ config:app:set files_antivirus av_infected_action --value="only_log"
        if [ -n "$CLAMAV_BLOCKLISTED_DIRECTORIES" ]; then
            php /var/www/html/occ config:app:set files_antivirus av_blocklisted_directories --value="$CLAMAV_BLOCKLISTED_DIRECTORIES"
        fi
    fi
else
    if [ "$REMOVE_DISABLED_APPS" = yes ] && [ -d "/var/www/html/custom_apps/files_antivirus" ]; then
        php /var/www/html/occ app:remove files_antivirus
    fi
fi

# Imaginary
if [ "$IMAGINARY_ENABLED" = 'yes' ]; then
    php /var/www/html/occ config:system:set enabledPreviewProviders 0 --value="OC\\Preview\\Imaginary"
    php /var/www/html/occ config:system:set enabledPreviewProviders 23 --value="OC\\Preview\\ImaginaryPDF"
    php /var/www/html/occ config:system:set preview_imaginary_url --value="http://$IMAGINARY_HOST:9000"
    php /var/www/html/occ config:system:set preview_imaginary_key --value="$IMAGINARY_SECRET"
else
    if [ -n "$(php /var/www/html/occ config:system:get preview_imaginary_url)" ]; then
        php /var/www/html/occ config:system:delete enabledPreviewProviders 0
        php /var/www/html/occ config:system:delete preview_imaginary_url
        php /var/www/html/occ config:system:delete enabledPreviewProviders 20
        php /var/www/html/occ config:system:delete enabledPreviewProviders 21
        php /var/www/html/occ config:system:delete enabledPreviewProviders 22
        php /var/www/html/occ config:system:delete enabledPreviewProviders 23
    fi
fi

# Fulltextsearch
if [ "$FULLTEXTSEARCH_ENABLED" = 'yes' ]; then
    count=0
    while ! nc -z "$FULLTEXTSEARCH_HOST" "$FULLTEXTSEARCH_PORT" && [ "$count" -lt 90 ]; do
        echo "Waiting for Fulltextsearch to become available..."
        count=$((count+5))
        sleep 5
    done
    if [ "$count" -ge 90 ]; then
        echo "Fulltextsearch did not start in time. Skipping initialization and disabling fulltextsearch apps."
        php /var/www/html/occ app:disable fulltextsearch
        php /var/www/html/occ app:disable fulltextsearch_elasticsearch
        php /var/www/html/occ app:disable files_fulltextsearch
    else
        if ! [ -d "/var/www/html/custom_apps/fulltextsearch" ]; then
            php /var/www/html/occ app:install fulltextsearch
        elif [ "$(php /var/www/html/occ config:app:get fulltextsearch enabled)" != "yes" ]; then
            php /var/www/html/occ app:enable fulltextsearch
        elif [ "$SKIP_UPDATE" != 1 ]; then
            php /var/www/html/occ app:update fulltextsearch
        fi    
        if ! [ -d "/var/www/html/custom_apps/fulltextsearch_elasticsearch" ]; then
            php /var/www/html/occ app:install fulltextsearch_elasticsearch
        elif [ "$(php /var/www/html/occ config:app:get fulltextsearch_elasticsearch enabled)" != "yes" ]; then
            php /var/www/html/occ app:enable fulltextsearch_elasticsearch
        elif [ "$SKIP_UPDATE" != 1 ]; then
            php /var/www/html/occ app:update fulltextsearch_elasticsearch
        fi    
        if ! [ -d "/var/www/html/custom_apps/files_fulltextsearch" ]; then
            php /var/www/html/occ app:install files_fulltextsearch
        elif [ "$(php /var/www/html/occ config:app:get files_fulltextsearch enabled)" != "yes" ]; then
            php /var/www/html/occ app:enable files_fulltextsearch
        elif [ "$SKIP_UPDATE" != 1 ]; then
            php /var/www/html/occ app:update files_fulltextsearch
        fi
        php /var/www/html/occ fulltextsearch:configure '{"search_platform":"OCA\\FullTextSearch_Elasticsearch\\Platform\\ElasticSearchPlatform"}'
        php /var/www/html/occ fulltextsearch_elasticsearch:configure "{\"elastic_host\":\"http://$FULLTEXTSEARCH_USER:$FULLTEXTSEARCH_PASSWORD@$FULLTEXTSEARCH_HOST:$FULLTEXTSEARCH_PORT\",\"elastic_index\":\"$FULLTEXTSEARCH_INDEX\"}"
        php /var/www/html/occ files_fulltextsearch:configure "{\"files_pdf\":true,\"files_office\":true}"

        # Do the index
        if ! [ -f "$NEXTCLOUD_DATA_DIR/fts-index.done" ]; then
            echo "Waiting 10 seconds before activating fulltextsearch..."
            sleep 10
            echo "Activating fulltextsearch..."
            if php /var/www/html/occ fulltextsearch:test && php /var/www/html/occ fulltextsearch:index "{\"errors\": \"reset\"}" --no-readline; then
                touch "$NEXTCLOUD_DATA_DIR/fts-index.done"
            else
                echo "Fulltextsearch failed. Could not index."
                echo "If you want to skip indexing in the future, see https://github.com/nextcloud/all-in-one/discussions/1709"
            fi
        fi
    fi
else
    if [ "$REMOVE_DISABLED_APPS" = yes ]; then
        if [ -d "/var/www/html/custom_apps/fulltextsearch" ]; then
            php /var/www/html/occ app:remove fulltextsearch
        fi
        if [ -d "/var/www/html/custom_apps/fulltextsearch_elasticsearch" ]; then
            php /var/www/html/occ app:remove fulltextsearch_elasticsearch
        fi
        if [ -d "/var/www/html/custom_apps/files_fulltextsearch" ]; then
            php /var/www/html/occ app:remove files_fulltextsearch
        fi
    fi
fi

# Docker socket proxy
# app_api is a shipped app
if [ -d "/var/www/html/custom_apps/app_api" ]; then
    php /var/www/html/occ app:disable app_api
    rm -r "/var/www/html/custom_apps/app_api"
fi
if [ "$DOCKER_SOCKET_PROXY_ENABLED" = 'yes' ]; then
    if [ "$(php /var/www/html/occ config:app:get app_api enabled)" != "yes" ]; then
        php /var/www/html/occ app:enable app_api
    fi
else
    if [ "$REMOVE_DISABLED_APPS" = yes ]; then
        if [ "$(php /var/www/html/occ config:app:get app_api enabled)" != "no" ]; then
            php /var/www/html/occ app:disable app_api
        fi
    fi
fi

# Whiteboard app
if [ "$WHITEBOARD_ENABLED" = 'yes' ]; then
    if ! [ -d "/var/www/html/custom_apps/whiteboard" ]; then
        php /var/www/html/occ app:install whiteboard
    elif [ "$(php /var/www/html/occ config:app:get whiteboard enabled)" != "yes" ]; then
        php /var/www/html/occ app:enable whiteboard
    elif [ "$SKIP_UPDATE" != 1 ]; then
        php /var/www/html/occ app:update whiteboard
    fi
    php /var/www/html/occ config:app:set whiteboard collabBackendUrl --value="https://$NC_DOMAIN/whiteboard"
    php /var/www/html/occ config:app:set whiteboard jwt_secret_key --value="$WHITEBOARD_SECRET"
else
    if [ "$REMOVE_DISABLED_APPS" = yes ] && [ -d "/var/www/html/custom_apps/whiteboard" ]; then
        php /var/www/html/occ app:remove whiteboard
    fi
fi

# Remove the update skip file always
rm -f "$NEXTCLOUD_DATA_DIR"/skip.update
