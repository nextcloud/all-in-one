#!/bin/bash

PHP_CLI="php"
if [[ "$EUID" = 0 ]]; then
    PHP_CLI="su-exec www-data $PHP_CLI"
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

# Make sure updater channel is set to "stable"
$PHP_CLI /var/www/html/occ config:system:set update_channel --value="stable"

run_updater() {
    $PHP_CLI /var/www/html/updater/updater.phar --no-interaction --no-backup
    # The updater exits with 255 (-1) if one of its steps failed, which might leave the code in an inconsistent state.
    # Other non-zero exit codes come from "occ upgrade" or from disabling maintenance mode, which can be retried here.
    if [ "$?" -eq 255 ] || ! $PHP_CLI /var/www/html/occ -V; then
        return 1
    fi
    if $PHP_CLI /var/www/html/occ status | grep needsDbUpgrade | grep -q 'true'; then
        $PHP_CLI /var/www/html/occ upgrade || return 1
    fi
    if $PHP_CLI /var/www/html/occ status | grep maintenance | grep -q 'true'; then
        $PHP_CLI /var/www/html/occ maintenance:mode --off || return 1
    fi
}

# Run the update
if ! run_updater; then
    echo "Upgrade of Nextcloud failed! Check the output above and the updater.log in the Nextcloud data directory for details."
    exit 1
fi
# shellcheck disable=SC2016
installed_version="$($PHP_CLI -r 'require "/var/www/html/version.php"; echo implode(".", $OC_Version);')"
export INSTALLED_MAJOR="${installed_version%%.*}"
# If a valid upgrade path, trigger the Nextcloud built-in Updater
if ! $PHP_CLI -r "version_compare(getenv('INSTALLED_MAJOR'), getenv('IMAGE_MAJOR'), '>') || exit(1);"; then
    if ! run_updater; then
        echo "Upgrade of Nextcloud failed! Check the output above and the updater.log in the Nextcloud data directory for details."
        exit 1
    fi
fi
$PHP_CLI /var/www/html/occ config:system:set updatechecker --type=bool --value=true
$PHP_CLI /var/www/html/occ app:enable nextcloud-aio --force
$PHP_CLI /var/www/html/occ db:add-missing-columns
$PHP_CLI /var/www/html/occ db:add-missing-primary-keys
yes | $PHP_CLI /var/www/html/occ db:convert-filecache-bigint
