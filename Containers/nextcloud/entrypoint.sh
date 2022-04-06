#!/bin/bash

# version_greater A B returns whether A > B
version_greater() {
    [ "$(printf '%s\n' "$@" | sort -t '.' -n -k1,1 -k2,2 -k3,3 -k4,4 | head -n 1)" != "$1" ]
}

# return true if specified directory is empty
directory_empty() {
    [ -z "$(ls -A "$1/")" ]
}

echo "Configuring Redis as session handler"
cat << REDIS_CONF > /usr/local/etc/php/conf.d/redis-session.ini
session.save_handler = redis
session.save_path = "tcp://${REDIS_HOST}:${REDIS_HOST_PORT:=6379}?auth=${REDIS_HOST_PASSWORD}"
redis.session.locking_enabled = 1
redis.session.lock_retries = -1
# redis.session.lock_wait_time is specified in microseconds.
# Wait 10ms before retrying the lock rather than the default 2ms.
redis.session.lock_wait_time = 10000
REDIS_CONF

# Check permissions in ncdata
touch "/mnt/ncdata/this-is-a-test-file"
if ! [ -f "/mnt/ncdata/this-is-a-test-file" ]; then
    echo "The www-data user doesn't seem to have access rights in /mnt/ncdata.
Did you maybe change the datadir and did forget to apply the correct permissions?"
    exit 1
fi
rm "/mnt/ncdata/this-is-a-test-file"

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

# Skip any update if Nextcloud was just restored
if ! [ -f "/mnt/ncdata/skip.update" ]; then
    if version_greater "$image_version" "$installed_version"; then
        # Check if it skips a major version
        INSTALLED_MAJOR="${installed_version%%.*}"
        IMAGE_MAJOR="${image_version%%.*}"
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
            php /var/www/html/occ app:list | sed -n "/Enabled:/,/Disabled:/p" > /tmp/list_before

            if [ "$((IMAGE_MAJOR - INSTALLED_MAJOR))" -eq 1 ]; then
                php /var/www/html/occ config:system:delete app_install_overwrite
            fi

            php /var/www/html/occ app:update --all

            # Fix removing the updatenotification for old instances
            if [ -d "/var/www/html/apps/updatenotification" ]; then
                php /var/www/html/occ app:disable updatenotification
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
            php /var/www/html/occ config:system:set logfile --value="/var/log/nextcloud/nextcloud.log"
            php /var/www/html/occ config:system:set log_rotate_size --value="10485760"
            php /var/www/html/occ app:enable admin_audit
            php /var/www/html/occ config:app:set admin_audit logfile --value="/var/log/nextcloud/audit.log"
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
            php /var/www/html/occ app:install twofactor_totp
            php /var/www/html/occ app:install deck
            php /var/www/html/occ app:install tasks
            php /var/www/html/occ app:install calendar
            php /var/www/html/occ app:install contacts
            php /var/www/html/occ app:install apporder

        #upgrade
        else
            while [ -n "$(pgrep -f cron.php)" ]
            do
                echo "Waiting for Nextclouds cronjob to finish..."
                sleep 5
            done

            echo "Upgrading nextcloud from $installed_version to $image_version..."
            if ! php /var/www/html/occ upgrade || ! php /var/www/html/occ -V; then
                echo "Upgrade failed. Please restore from backup."
                exit 1
            fi

            php /var/www/html/occ app:list | sed -n "/Enabled:/,/Disabled:/p" > /tmp/list_after
            echo "The following apps have been disabled:"
            diff /tmp/list_before /tmp/list_after | grep '<' | cut -d- -f2 | cut -d: -f1
            rm -f /tmp/list_before /tmp/list_after

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

    # Performing update of all apps if daily backups are enabled, running and successful
    if [ "$DAILY_BACKUP_RUNNING" = 'yes' ]; then
        php /var/www/html/occ app:update --all
    fi
fi

# Check if appdata is present
# If not, something broke (e.g. changing ncdatadir after aio was first started)
if [ -z "$(find "/mnt/ncdata/" -maxdepth 1 -mindepth 1 -type d -name "appdata_*")" ]; then
    echo "Appdata is not present. Did you maybe change the datadir after aio was first started?"
    exit 1
fi

# Apply one-click-instance settings
echo "Applying one-click-instance settings..."
php /var/www/html/occ config:system:set one-click-instance --value=true --type=bool
php /var/www/html/occ config:system:set one-click-instance.user-limit --value=100 --type=int

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
else
    php /var/www/html/occ app:update notify_push
fi
php /var/www/html/occ config:system:set trusted_proxies 0 --value="127.0.0.1"
php /var/www/html/occ config:app:set notify_push base_endpoint --value="https://$NC_DOMAIN/push"

# Collabora
if [ "$COLLABORA_ENABLED" = 'yes' ]; then
    while ! nc -z "$COLLABORA_HOST" 9980; do
        echo "waiting for Collabora to become available..."
        sleep 5
    done
    if ! [ -d "/var/www/html/custom_apps/richdocuments" ]; then
        php /var/www/html/occ app:install richdocuments
    elif [ "$(php /var/www/html/occ config:app:get richdocuments enabled)" = "no" ]; then
        php /var/www/html/occ app:enable richdocuments
    else
        php /var/www/html/occ app:update richdocuments
    fi
    # Fix https://github.com/nextcloud/all-in-one/issues/188:
    php /var/www/html/occ config:system:set allow_local_remote_servers --type=bool --value=true
    php /var/www/html/occ config:app:set richdocuments wopi_url --value="http://$COLLABORA_HOST:9980/"
    php /var/www/html/occ config:app:set richdocuments public_wopi_url --value="https://$NC_DOMAIN/"
    php /var/www/html/occ richdocuments:activate-config
else
    if [ -d "/var/www/html/custom_apps/richdocuments" ]; then
        php /var/www/html/occ config:system:delete allow_local_remote_servers
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
    else
        php /var/www/html/occ app:update onlyoffice
    fi
    php /var/www/html/occ config:app:set onlyoffice DocumentServerUrl --value="https://$NC_DOMAIN/onlyoffice"
else
    if [ -d "/var/www/html/custom_apps/onlyoffice" ]; then
        php /var/www/html/occ app:remove onlyoffice
    fi
fi

# Talk
if [ "$TALK_ENABLED" = 'yes' ]; then
    if ! [ -d "/var/www/html/custom_apps/spreed" ]; then
        php /var/www/html/occ app:install spreed
    elif [ "$(php /var/www/html/occ config:app:get spreed enabled)" = "no" ]; then
        php /var/www/html/occ app:enable spreed
    else
        php /var/www/html/occ app:update spreed
    fi
    STUN_SERVERS="[\"$NC_DOMAIN:3478\"]"
    TURN_SERVERS="[{\"server\":\"$NC_DOMAIN:3478\",\"secret\":\"$TURN_SECRET\",\"protocols\":\"udp,tcp\"}]"
    SIGNALING_SERVERS="{\"servers\":[{\"server\":\"https://$NC_DOMAIN/standalone-signaling/\",\"verify\":true}],\"secret\":\"$SIGNALING_SECRET\"}"
    php /var/www/html/occ config:app:set spreed stun_servers --value="$STUN_SERVERS" --output json
    php /var/www/html/occ config:app:set spreed turn_servers --value="$TURN_SERVERS" --output json
    php /var/www/html/occ config:app:set spreed signaling_servers --value="$SIGNALING_SERVERS" --output json
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
    else
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

# Remove the update skip file always
rm -f /mnt/ncdata/skip.update
