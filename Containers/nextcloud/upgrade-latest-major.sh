#!/bin/bash

PHP_CLI="php"
if [[ "$EUID" = 0 ]]; then
    PHP_CLI="sudo -u www-data -E $PHP_CLI"
fi

# shellcheck disable=SC2016
image_version="$($PHP_CLI -r 'require "/var/www/html/version.php"; echo implode(".", $OC_Version);')"
export IMAGE_MAJOR="${image_version%%.*}"

$PHP_CLI /var/www/html/occ config:system:set updatedirectory --value="/nc-updater"
INSTALLED_AT="$($PHP_CLI /var/www/html/occ config:app:get core installedat)"
if [ -n "${INSTALLED_AT}" ]; then
    # Set the installedat to 00 which will allow to skip staging and install the next major directly
    # shellcheck disable=SC2001
    INSTALLED_AT="$(echo "${INSTALLED_AT}" | sed "s|[0-9][0-9]$|00|")"
    $PHP_CLI /var/www/html/occ config:app:set core installedat --value="${INSTALLED_AT}"
fi
$PHP_CLI /var/www/html/updater/updater.phar --no-interaction --no-backup
if ! $PHP_CLI /var/www/html/occ -V || $PHP_CLI /var/www/html/occ status | grep maintenance | grep -q 'true'; then
    echo "Installation of Nextcloud failed!"
    touch "$NEXTCLOUD_DATA_DIR/install.failed"
    exit 1
fi
# shellcheck disable=SC2016
installed_version="$($PHP_CLI -r 'require "/var/www/html/version.php"; echo implode(".", $OC_Version);')"
export INSTALLED_MAJOR="${installed_version%%.*}"
# If a valid upgrade path, trigger the Nextcloud built-in Updater
if ! $PHP_CLI -r "version_compare(getenv('INSTALLED_MAJOR'), getenv('IMAGE_MAJOR'), '>') || exit(1);"; then
    $PHP_CLI /var/www/html/updater/updater.phar --no-interaction --no-backup
    if ! $PHP_CLI /var/www/html/occ -V || $PHP_CLI /var/www/html/occ status | grep maintenance | grep -q 'true'; then
        echo "Installation of Nextcloud failed!"
        # TODO: Add a hint here about what to do / where to look / updater.log?
        touch "$NEXTCLOUD_DATA_DIR/install.failed"
        exit 1
    fi
fi
$PHP_CLI /var/www/html/occ config:system:set updatechecker --type=bool --value=true
$PHP_CLI /var/www/html/occ app:enable nextcloud-aio --force
$PHP_CLI /var/www/html/occ db:add-missing-columns
$PHP_CLI /var/www/html/occ db:add-missing-primary-keys
yes | $PHP_CLI /var/www/html/occ db:convert-filecache-bigint
