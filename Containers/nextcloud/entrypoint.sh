#!/bin/bash

# version_greater A B returns whether A > B
version_greater() {
    [ "$(printf '%s\n' "$@" | sort -t '.' -n -k1,1 -k2,2 -k3,3 -k4,4 | head -n 1)" != "$1" ]
}

# return true if specified directory is empty
directory_empty() {
    [ -z "$(ls -A "$1/")" ]
}

echo "Configuring Redis as session handler..."
cat << REDIS_CONF > /usr/local/etc/php/conf.d/redis-session.ini
session.save_handler = redis
session.save_path = "tcp://${REDIS_HOST}:${REDIS_HOST_PORT:=6379}?auth=${REDIS_HOST_PASSWORD}"
redis.session.locking_enabled = 1
redis.session.lock_retries = -1
# redis.session.lock_wait_time is specified in microseconds.
# Wait 10ms before retrying the lock rather than the default 2ms.
redis.session.lock_wait_time = 10000
REDIS_CONF

echo "Setting php max children..."
MEMORY=$(mawk '/MemTotal/ {printf "%d", $2/1024}' /proc/meminfo)
PHP_MAX_CHILDREN=$((MEMORY/50))
if [ -n "$PHP_MAX_CHILDREN" ]; then
    sed -i "s/^pm.max_children =.*/pm.max_children = $PHP_MAX_CHILDREN/" /usr/local/etc/php-fpm.d/www.conf
fi

# Check permissions in ncdata
touch "$NEXTCLOUD_DATA_DIR/this-is-a-test-file" &>/dev/null
if ! [ -f "$NEXTCLOUD_DATA_DIR/this-is-a-test-file" ]; then
    echo "The www-data user doesn't seem to have access rights in the datadir.
Most likely are the files located on a drive that does not follow linux permissions.
Please adjust the permissions like mentioned below.
The found permissions are:
$(stat -c "%u:%g %a" "$NEXTCLOUD_DATA_DIR")
(userID:groupID permissions)
but they should be:
33:0 750
(userID:groupID permissions)"
    exit 1
fi
rm "$NEXTCLOUD_DATA_DIR/this-is-a-test-file"

if [ -f /var/www/html/version.php ]; then
    # shellcheck disable=SC2016
    installed_version="$(php -r 'require "/var/www/html/version.php"; echo implode(".", $OC_Version);')"
else
    installed_version="0.0.0.0"
fi
if [ -f "/usr/src/nextcloud/version.php" ]; then
    # shellcheck disable=SC2016
    image_version="$(php -r 'require "/usr/src/nextcloud/version.php"; echo implode(".", $OC_Version);')"
else
    image_version="$installed_version"
fi

# unset admin password
if [ "$installed_version" != "0.0.0.0" ]; then
    unset ADMIN_PASSWORD
fi

# Don't start the container if Nextcloud is not compatible with the PHP version
if [ -f "/var/www/html/lib/versioncheck.php" ] && ! php /var/www/html/lib/versioncheck.php; then
    echo "It seems like your installed Nextcloud is not compatible with the by the container provided PHP version."
    echo "This most likely happened because you tried to restore an old Nextcloud version from backup that is not compatible with the PHP version that comes with the container."
    echo "Please try to restore a more recent backup which contains a Nextcloud version that is compatible with the PHP version that comes with the container."
    echo "If you do not have a more recent backup, feel free to have a look at this documentation: https://github.com/nextcloud/all-in-one/blob/main/manual-upgrade.md"
    exit 1
fi

# Do not start the container if the last update failed
if [ -f "$NEXTCLOUD_DATA_DIR/update.failed" ]; then
    echo "The last Nextcloud update failed."
    echo "Please restore from backup and try again!"
    echo "If you do not have a backup in place, you can simply delete the update.failed file in the datadir which will allow the container to start again."
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
            rm -rf "$GNUPGHOME" /usr/src/tmp/nextcloud/updater
            mkdir -p /usr/src/tmp/nextcloud/data
            mkdir -p /usr/src/tmp/nextcloud/custom_apps
            chmod +x /usr/src/tmp/nextcloud/occ
            cp /usr/src/nextcloud/config/* /usr/src/tmp/nextcloud/config/
            mkdir -p /usr/src/tmp/nextcloud/apps/nextcloud-aio
            cp /usr/src/nextcloud/apps/nextcloud-aio/* /usr/src/tmp/nextcloud/apps/nextcloud-aio/
            mv /usr/src/nextcloud /usr/src/temp-nextcloud
            mv /usr/src/tmp/nextcloud /usr/src/nextcloud
            rm -r /usr/src/tmp
            rm -r /usr/src/temp-nextcloud
            # shellcheck disable=SC2016
            image_version="$(php -r 'require "/usr/src/nextcloud/version.php"; echo implode(".", $OC_Version);')"
            IMAGE_MAJOR="${image_version%%.*}"
            set +ex
        fi

        if [ "$installed_version" != "0.0.0.0" ]; then
            while true; do
                echo -e "Checking connection to appstore"
                CURL_STATUS="$(curl -LI "https://apps.nextcloud.com/" -o /dev/null -w '%{http_code}\n' -s)"
                if [[ "$CURL_STATUS" = "200" ]]
                then
                    echo "Appstore is reachable"
                    break
                else
                    echo "Curl didn't produce a 200 status, is appstore reachable?"
                    sleep 5
                fi
            done

            php /var/www/html/occ maintenance:mode --off

            echo "Getting and backing up the status of apps for later, this might take a while..."
            NC_APPS="$(find /var/www/html/custom_apps/ -type d -maxdepth 1 -mindepth 1 | sed 's|/var/www/html/custom_apps/||g')"
            if [ -z "$NC_APPS" ]; then
                echo "No apps detected, aborting export of app status..."
                APPSTORAGE="no-export-done"
            else
                read -ra NC_APPS_ARRAY <<< "$NC_APPS"
                declare -Ag APPSTORAGE
                echo "Disabling apps before the update in order to make the update procedure more safe. This can take a while..."
                for app in "${NC_APPS_ARRAY[@]}"; do
                    APPSTORAGE[$app]=$(php /var/www/html/occ config:app:get "$app" enabled)
                    php /var/www/html/occ app:disable "$app"
                done
            fi

            if [ "$((IMAGE_MAJOR - INSTALLED_MAJOR))" -eq 1 ]; then
                php /var/www/html/occ config:system:delete app_install_overwrite
            fi

            php /var/www/html/occ app:update --all

            # Fix removing the updatenotification for old instances
            UPDATENOTIFICATION_STATUS="$(php /var/www/html/occ config:app:get updatenotification enabled)"
            if [ -d "/var/www/html/apps/updatenotification" ]; then
                php /var/www/html/occ app:disable updatenotification
            elif [ "$UPDATENOTIFICATION_STATUS" != "no" ] && [ -n "$UPDATENOTIFICATION_STATUS" ]; then
                php /var/www/html/occ config:app:set updatenotification enabled --value="no"
            fi
        fi

        echo "Initializing nextcloud $image_version ..."
        rsync -rlD --delete --exclude-from=/upgrade.exclude /usr/src/nextcloud/ /var/www/html/

        for dir in config data custom_apps themes; do
            if [ ! -d "/var/www/html/$dir" ] || directory_empty "/var/www/html/$dir"; then
                rsync -rlD --include "/$dir/" --exclude '/*' /usr/src/nextcloud/ /var/www/html/
            fi
        done
        rsync -rlD --include '/version.php' --exclude '/*' /usr/src/nextcloud/ /var/www/html/
        echo "Initializing finished"

        #install
        if [ "$installed_version" = "0.0.0.0" ]; then
            echo "New nextcloud instance"

            INSTALL_OPTIONS=(-n --admin-user "$ADMIN_USER" --admin-pass "$ADMIN_PASSWORD")
            if [ -n "${NEXTCLOUD_DATA_DIR}" ]; then
                INSTALL_OPTIONS+=(--data-dir "$NEXTCLOUD_DATA_DIR")
            fi

            echo "Installing with PostgreSQL database"
            INSTALL_OPTIONS+=(--database pgsql --database-name "$POSTGRES_DB" --database-user "$POSTGRES_USER" --database-pass "$POSTGRES_PASSWORD" --database-host "$POSTGRES_HOST")

            echo "starting nextcloud installation"
            max_retries=10
            try=0
            until php /var/www/html/occ maintenance:install "${INSTALL_OPTIONS[@]}" || [ "$try" -gt "$max_retries" ]
            do
                echo "retrying install..."
                try=$((try+1))
                sleep 10s
            done
            if [ "$try" -gt "$max_retries" ]; then
                echo "installing of nextcloud failed!"
                exit 1
            fi

            # unset admin password
            unset ADMIN_PASSWORD

            # Apply log settings
            echo "Applying default settings..."
            mkdir -p /var/www/html/data
            php /var/www/html/occ config:system:set loglevel --value=2
            php /var/www/html/occ config:system:set log_type --value=file
            php /var/www/html/occ config:system:set logfile --value="/var/www/html/data/nextcloud.log"
            php /var/www/html/occ config:system:set log_rotate_size --value="10485760"
            php /var/www/html/occ app:enable admin_audit
            php /var/www/html/occ config:app:set admin_audit logfile --value="/var/www/html/data/audit.log"
            php /var/www/html/occ config:system:set log.condition apps 0 --value="admin_audit"

            # Apply preview settings
            echo "Applying preview settings..."
            php /var/www/html/occ config:system:set preview_max_x --value="2048"
            php /var/www/html/occ config:system:set preview_max_y --value="2048"
            php /var/www/html/occ config:system:set jpeg_quality --value="60"
            php /var/www/html/occ config:app:set preview jpeg_quality --value="60"
            php /var/www/html/occ config:system:delete enabledPreviewProviders
            php /var/www/html/occ config:system:set enabledPreviewProviders 1 --value="OC\\Preview\\Image"
            php /var/www/html/occ config:system:set enabledPreviewProviders 2 --value="OC\\Preview\\MarkDown"
            php /var/www/html/occ config:system:set enabledPreviewProviders 3 --value="OC\\Preview\\MP3"
            php /var/www/html/occ config:system:set enabledPreviewProviders 4 --value="OC\\Preview\\TXT"
            php /var/www/html/occ config:system:set enabledPreviewProviders 5 --value="OC\\Preview\\OpenDocument"
            php /var/www/html/occ config:system:set enabledPreviewProviders 6 --value="OC\\Preview\\Movie"
            php /var/www/html/occ config:system:set enable_previews --value=true --type=boolean

            # Apply other settings
            echo "Applying other settings..."
            php /var/www/html/occ config:system:set upgrade.disable-web --type=bool --value=true
            php /var/www/html/occ config:system:set mail_smtpmode --value="smtp"
            php /var/www/html/occ config:system:set trashbin_retention_obligation --value="auto, 30"
            php /var/www/html/occ config:system:set versions_retention_obligation --value="auto, 30"
            php /var/www/html/occ config:system:set activity_expire_days --value="30"
            php /var/www/html/occ config:system:set simpleSignUpLink.shown --type=bool --value=false
            php /var/www/html/occ config:system:set share_folder --value="/Shared"
            # Not needed anymore with the removal of the updatenotification app:
            # php /var/www/html/occ config:app:set updatenotification notify_groups --value="[]"

            # Install some apps by default
            if [ -n "$STARTUP_APPS" ]; then
                read -ra STARTUP_APPS_ARRAY <<< "$STARTUP_APPS"
                for app in "${STARTUP_APPS_ARRAY[@]}"; do
                    php /var/www/html/occ app:install "$app"
                done
            fi

        #upgrade
        else
            echo "Upgrading nextcloud from $installed_version to $image_version..."
            if ! php /var/www/html/occ upgrade || ! php /var/www/html/occ -V; then
                echo "Upgrade failed. Please restore from backup."
                bash /notify.sh "Nextcloud update to $image_version failed!" "Please restore from backup!"
                exit 1
            fi

            rm "$NEXTCLOUD_DATA_DIR/update.failed"
            bash /notify.sh "Nextcloud update to $image_version successful!" "Feel free to inspect the Nextcloud container logs for more info."

            php /var/www/html/occ app:update --all

            # Restore app status
            if [ "${APPSTORAGE[0]}" != "no-export-done" ]; then
                echo "Restoring the status of apps. This can take a while..."
                for app in "${!APPSTORAGE[@]}"; do
                    if [ -n "${APPSTORAGE[$app]}" ]; then
                        if [ "${APPSTORAGE[$app]}" != "no" ]; then
                            echo "Enabling $app..."
                            if ! php /var/www/html/occ app:enable "$app" >/dev/null; then
                                echo "$app could not get enabled. Probably because it is not compatible with the new Nextcloud version."
                                bash /notify.sh "Could not enable the $app after the Nextcloud update!" "Feel free to look at the Nextcloud update logs and force-enable the app again from the app-store UI."
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

            # Apply optimization
            echo "Doing some optimizations..."
            php /var/www/html/occ maintenance:repair
            php /var/www/html/occ db:add-missing-indices
            php /var/www/html/occ db:add-missing-columns
            php /var/www/html/occ db:add-missing-primary-keys
            yes | php /var/www/html/occ db:convert-filecache-bigint
            php /var/www/html/occ maintenance:mimetype:update-js
            php /var/www/html/occ maintenance:mimetype:update-db
        fi
    fi

    # Performing update of all apps if daily backups are enabled, running and successful and if it is saturday
    if [ "$UPDATE_NEXTCLOUD_APPS" = 'yes' ] && [ "$(date +%u)" = 6 ]; then
        UPDATED_APPS="$(php /var/www/html/occ app:update --all)"
        if [ -n "$UPDATED_APPS" ]; then
             bash /notify.sh "Your apps just got updated!" "$UPDATED_APPS"
        fi
    fi
else
    SKIP_UPDATE=1
fi

# Check if appdata is present
# If not, something broke (e.g. changing ncdatadir after aio was first started)
if [ -z "$(find "$NEXTCLOUD_DATA_DIR/" -maxdepth 1 -mindepth 1 -type d -name "appdata_*")" ]; then
    echo "Appdata is not present. Did you maybe change the datadir after aio was first started?"
    exit 1
fi

# Configure tempdirectory
if [ -z "$OBJECTSTORE_S3_BUCKET" ] && [ -z "$OBJECTSTORE_SWIFT_URL" ]; then
    mkdir -p "$NEXTCLOUD_DATA_DIR/tmp/"
    if ! grep -q upload_tmp_dir /usr/local/etc/php/conf.d/nextcloud.ini; then
        echo "upload_tmp_dir = $NEXTCLOUD_DATA_DIR/tmp/" >> /usr/local/etc/php/conf.d/nextcloud.ini
    fi
    php /var/www/html/occ config:system:set tempdirectory --value="$NEXTCLOUD_DATA_DIR/tmp/"
fi

# Perform fingerprint update if instance was restored
if [ -f "$NEXTCLOUD_DATA_DIR/fingerprint.update" ]; then
    php /var/www/html/occ maintenance:data-fingerprint
    rm "$NEXTCLOUD_DATA_DIR/fingerprint.update"
fi

# Apply one-click-instance settings
echo "Applying one-click-instance settings..."
php /var/www/html/occ config:system:set one-click-instance --value=true --type=bool
php /var/www/html/occ config:system:set one-click-instance.user-limit --value=100 --type=int
php /var/www/html/occ config:system:set one-click-instance.link --value="https://nextcloud.com/all-in-one/"
php /var/www/html/occ app:enable support

# Adjusting log files to be stored on a volume
echo "Adjusting log files..."
php /var/www/html/occ config:system:set logfile --value="/var/www/html/data/nextcloud.log"
php /var/www/html/occ config:app:set admin_audit logfile --value="/var/www/html/data/audit.log"

# Apply network settings
echo "Applying network settings..."
php /var/www/html/occ config:system:set trusted_domains 1 --value="$NC_DOMAIN"
php /var/www/html/occ config:system:set overwrite.cli.url --value="https://$NC_DOMAIN/"
php /var/www/html/occ config:system:set htaccess.RewriteBase --value="/"
php /var/www/html/occ maintenance:update:htaccess

# Disallow creating local external storages when nothing was mounted
if [ -z "$NEXTCLOUD_MOUNT" ]; then
    php /var/www/html/occ config:system:set files_external_allow_create_new_local --type=bool --value=false
else
    php /var/www/html/occ config:system:set files_external_allow_create_new_local --type=bool --value=true
fi

# AIO app
if [ "$(php /var/www/html/occ config:app:get nextcloud-aio enabled)" = "" ]; then
    php /var/www/html/occ app:enable nextcloud-aio
elif [ "$(php /var/www/html/occ config:app:get nextcloud-aio enabled)" = "no" ]; then
    php /var/www/html/occ app:enable nextcloud-aio
fi

# Notify push
if ! [ -d "/var/www/html/custom_apps/notify_push" ]; then
    php /var/www/html/occ app:install notify_push
elif [ "$(php /var/www/html/occ config:app:get notify_push enabled)" = "no" ]; then
    php /var/www/html/occ app:enable notify_push
elif [ "$SKIP_UPDATE" != 1 ]; then
    php /var/www/html/occ app:update notify_push
fi
php /var/www/html/occ config:system:set trusted_proxies 0 --value="127.0.0.1"
php /var/www/html/occ config:system:set trusted_proxies 1 --value="::1"
php /var/www/html/occ config:app:set notify_push base_endpoint --value="https://$NC_DOMAIN/push"

# Collabora
if [ "$COLLABORA_ENABLED" = 'yes' ]; then
    if ! [ -d "/var/www/html/custom_apps/richdocuments" ]; then
        php /var/www/html/occ app:install richdocuments
    elif [ "$(php /var/www/html/occ config:app:get richdocuments enabled)" = "no" ]; then
        php /var/www/html/occ app:enable richdocuments
    elif [ "$SKIP_UPDATE" != 1 ]; then
        php /var/www/html/occ app:update richdocuments
    fi
    php /var/www/html/occ config:app:set richdocuments wopi_url --value="https://$NC_DOMAIN/"
    # Fix https://github.com/nextcloud/all-in-one/issues/188:
    php /var/www/html/occ config:system:set allow_local_remote_servers --type=bool --value=true
else
    if [ -d "/var/www/html/custom_apps/richdocuments" ]; then
        php /var/www/html/occ app:remove richdocuments
    fi
fi

# OnlyOffice
if [ "$ONLYOFFICE_ENABLED" = 'yes' ]; then
    while ! nc -z "$ONLYOFFICE_HOST" 80; do
        echo "waiting for OnlyOffice to become available..."
        sleep 5
    done
    if ! [ -d "/var/www/html/custom_apps/onlyoffice" ]; then
        php /var/www/html/occ app:install onlyoffice
    elif [ "$(php /var/www/html/occ config:app:get onlyoffice enabled)" = "no" ]; then
        php /var/www/html/occ app:enable onlyoffice
    elif [ "$SKIP_UPDATE" != 1 ]; then
        php /var/www/html/occ app:update onlyoffice
    fi
    php /var/www/html/occ config:system:set onlyoffice jwt_secret --value="$ONLYOFFICE_SECRET"
    php /var/www/html/occ config:system:set onlyoffice jwt_header --value="AuthorizationJwt"
    php /var/www/html/occ config:app:set onlyoffice DocumentServerUrl --value="https://$NC_DOMAIN/onlyoffice"
    php /var/www/html/occ config:system:set allow_local_remote_servers --type=bool --value=true
else
    if [ -d "/var/www/html/custom_apps/onlyoffice" ] && [ -n "$ONLYOFFICE_SECRET" ] && [ "$(php /var/www/html/occ config:system:get onlyoffice jwt_secret)" = "$ONLYOFFICE_SECRET" ]; then
        php /var/www/html/occ app:remove onlyoffice
    fi
fi

# Talk
if [ "$TALK_ENABLED" = 'yes' ]; then
    if ! [ -d "/var/www/html/custom_apps/spreed" ]; then
        php /var/www/html/occ app:install spreed
    elif [ "$(php /var/www/html/occ config:app:get spreed enabled)" = "no" ]; then
        php /var/www/html/occ app:enable spreed
    elif [ "$SKIP_UPDATE" != 1 ]; then
        php /var/www/html/occ app:update spreed
    fi
    # Based on https://github.com/nextcloud/spreed/issues/960#issuecomment-416993435
    if [ -z "$(php /var/www/html/occ talk:turn:list --output="plain")" ]; then
        php /var/www/html/occ talk:turn:add "$NC_DOMAIN:$TALK_PORT" "udp,tcp" --secret="$TURN_SECRET"
    fi
    if php /var/www/html/occ talk:stun:list --output="plain" | grep -oP '[a-zA-Z.:0-9]+' | grep -q "^stun.nextcloud.com:443$"; then
        php /var/www/html/occ talk:stun:add "$NC_DOMAIN:$TALK_PORT"
        php /var/www/html/occ talk:stun:delete "stun.nextcloud.com:443"
    fi
    if ! php /var/www/html/occ talk:signaling:list --output="plain" | grep -q "https://$NC_DOMAIN/standalone-signaling/"; then
        php /var/www/html/occ talk:signaling:add "https://$NC_DOMAIN/standalone-signaling/" "$SIGNALING_SECRET" --verify
    fi
else
    if [ -d "/var/www/html/custom_apps/spreed" ]; then
        php /var/www/html/occ app:remove spreed
    fi
fi

# Clamav
if [ "$CLAMAV_ENABLED" = 'yes' ]; then
    while ! nc -z "$CLAMAV_HOST" 3310; do
        echo "waiting for clamav to become available..."
        sleep 5
    done
    if ! [ -d "/var/www/html/custom_apps/files_antivirus" ]; then
        php /var/www/html/occ app:install files_antivirus
    elif [ "$(php /var/www/html/occ config:app:get files_antivirus enabled)" = "no" ]; then
        php /var/www/html/occ app:enable files_antivirus
    elif [ "$SKIP_UPDATE" != 1 ]; then
        php /var/www/html/occ app:update files_antivirus
    fi
    php /var/www/html/occ config:app:set files_antivirus av_mode --value="daemon"
    php /var/www/html/occ config:app:set files_antivirus av_port --value="3310"
    php /var/www/html/occ config:app:set files_antivirus av_host --value="$CLAMAV_HOST"
    php /var/www/html/occ config:app:set files_antivirus av_stream_max_length --value="104857600"
    php /var/www/html/occ config:app:set files_antivirus av_max_file_size --value="-1"
    php /var/www/html/occ config:app:set files_antivirus av_infected_action --value="only_log"
else
    if [ -d "/var/www/html/custom_apps/files_antivirus" ]; then
        php /var/www/html/occ app:remove files_antivirus
    fi
fi

# Imaginary
if version_greater "$installed_version" "24.0.0.0"; then
    if [ "$IMAGINARY_ENABLED" = 'yes' ]; then
        php /var/www/html/occ config:system:set enabledPreviewProviders 0 --value="OC\\Preview\\Imaginary"
        php /var/www/html/occ config:system:set preview_imaginary_url --value="http://$IMAGINARY_HOST:9000"
    else
        php /var/www/html/occ config:system:delete enabledPreviewProviders 0
        php /var/www/html/occ config:system:delete preview_imaginary_url
    fi
fi

# Fulltextsearch
if [ "$FULLTEXTSEARCH_ENABLED" = 'yes' ]; then
    while ! nc -z "$FULLTEXTSEARCH_HOST" 9200; do
        echo "waiting for Fulltextsearch to become available..."
        sleep 5
    done
    if ! [ -d "/var/www/html/custom_apps/fulltextsearch" ]; then
        php /var/www/html/occ app:install fulltextsearch
    elif [ "$(php /var/www/html/occ config:app:get fulltextsearch enabled)" = "no" ]; then
        php /var/www/html/occ app:enable fulltextsearch
    elif [ "$SKIP_UPDATE" != 1 ]; then
        php /var/www/html/occ app:update fulltextsearch
    fi    
    if ! [ -d "/var/www/html/custom_apps/fulltextsearch_elasticsearch" ]; then
        php /var/www/html/occ app:install fulltextsearch_elasticsearch
    elif [ "$(php /var/www/html/occ config:app:get fulltextsearch_elasticsearch enabled)" = "no" ]; then
        php /var/www/html/occ app:enable fulltextsearch_elasticsearch
    elif [ "$SKIP_UPDATE" != 1 ]; then
        php /var/www/html/occ app:update fulltextsearch_elasticsearch
    fi    
    if ! [ -d "/var/www/html/custom_apps/files_fulltextsearch" ]; then
        php /var/www/html/occ app:install files_fulltextsearch
    elif [ "$(php /var/www/html/occ config:app:get files_fulltextsearch enabled)" = "no" ]; then
        php /var/www/html/occ app:enable files_fulltextsearch
    elif [ "$SKIP_UPDATE" != 1 ]; then
        php /var/www/html/occ app:update files_fulltextsearch
    fi
    php /var/www/html/occ fulltextsearch:configure '{"search_platform":"OCA\\FullTextSearch_Elasticsearch\\Platform\\ElasticSearchPlatform"}'
    php /var/www/html/occ fulltextsearch_elasticsearch:configure "{\"elastic_host\":\"http://$FULLTEXTSEARCH_HOST:9200\",\"elastic_index\":\"nextcloud-aio\"}"
    php /var/www/html/occ files_fulltextsearch:configure "{\"files_pdf\":\"1\",\"files_office\":\"1\"}"

    # Do the index
    if ! [ -f "$NEXTCLOUD_DATA_DIR/fts-index.done" ]; then
        echo "Waiting 10s before activating FTS..."
        sleep 10
        echo "Activating fulltextsearch..."
        if php /var/www/html/occ fulltextsearch:test && php /var/www/html/occ fulltextsearch:index; then
            touch "$NEXTCLOUD_DATA_DIR/fts-index.done"
        else
            echo "Fulltextsearch failed. Could not index."
        fi
    fi
else
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

# Remove the update skip file always
rm -f "$NEXTCLOUD_DATA_DIR"/skip.update
