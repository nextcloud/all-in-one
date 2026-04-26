#!/bin/bash

# shellcheck disable=SC2016
image_version="$(php -r 'require "/var/www/html/version.php"; echo implode(".", $OC_Version);')"
IMAGE_MAJOR="${image_version%%.*}"

php /var/www/html/occ config:system:set updatedirectory --value="/nc-updater"
INSTALLED_AT="$(php /var/www/html/occ config:app:get core installedat)"
if [ -n "${INSTALLED_AT}" ]; then
    # Set the installedat to 00 which will allow to skip staging and install the next major directly
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
