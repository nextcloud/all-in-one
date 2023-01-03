#!/bin/bash

# Only start container if database is accessible
while ! sudo -u www-data nc -z "$POSTGRES_HOST" 5432; do
    echo "Waiting for database to start..."
    sleep 5
done

# Use the correct Postgres username
POSTGRES_USER="oc_$POSTGRES_USER"
export POSTGRES_USER

# Fix false database connection on old instances
if [ -f "/var/www/html/config/config.php" ]; then
    sleep 2
    while ! sudo -u www-data psql -d "postgresql://$POSTGRES_USER:$POSTGRES_PASSWORD@$POSTGRES_HOST:5432/$POSTGRES_DB" -c "select now()"; do
        echo "Waiting for the database to start..."
        sleep 5
    done
    if [ "$POSTGRES_USER" = "oc_nextcloud" ] && echo "$POSTGRES_PASSWORD" | grep -q '^[a-z0-9]\+$'; then
        # this was introduced with https://github.com/nextcloud/all-in-one/pull/218
        sed -i "s|'dbuser'.*=>.*$|'dbuser' => '$POSTGRES_USER',|" /var/www/html/config/config.php
        sed -i "s|'dbpassword'.*=>.*$|'dbpassword' => '$POSTGRES_PASSWORD',|" /var/www/html/config/config.php
    fi
fi

# Trust additional Cacerts, if the user provided $TRUSTED_CACERTS_DIR
if [ -n "$TRUSTED_CACERTS_DIR" ]; then
    echo "User required to trust additional CA certificates, running 'update-ca-certificates.'"
    update-ca-certificates
fi

# Check if /dev/dri device is present and apply correct permissions
set -x
if ! [ -f "/dev-dri-group-was-added" ] && [ -n "$(find /dev -maxdepth 1 -mindepth 1 -name dri)" ] && [ -n "$(find /dev/dri -maxdepth 1 -mindepth 1 -name renderD128)" ]; then
    # From https://github.com/pulsejet/memories/wiki/QSV-Transcoding#docker-installations
    GID="$(stat -c "%g" /dev/dri/renderD128)"
    groupadd -g "$GID" render2 || true # sometimes this is needed
    GROUP="$(getent group "$GID" | cut -d: -f1)"
    usermod -aG "$GROUP" www-data
    touch "/dev-dri-group-was-added"
fi
set +x

# Check datadir permissions
sudo -u www-data touch "$NEXTCLOUD_DATA_DIR/this-is-a-test-file" &>/dev/null
if ! [ -f "$NEXTCLOUD_DATA_DIR/this-is-a-test-file" ]; then
    chown -R www-data:root "$NEXTCLOUD_DATA_DIR"
    chmod 750 -R "$NEXTCLOUD_DATA_DIR"
fi
sudo -u www-data rm -f "$NEXTCLOUD_DATA_DIR/this-is-a-test-file"

# Install additional dependencies
if [ -n "$ADDITIONAL_APKS" ]; then
    if ! [ -f "/additional-apks-are-installed" ]; then
        read -ra ADDITIONAL_APKS_ARRAY <<< "$ADDITIONAL_APKS"
        for app in "${ADDITIONAL_APKS_ARRAY[@]}"; do
            echo "Installing $app via apk..."
            if ! apk add --no-cache "$app" >/dev/null; then
                echo "The packet $app was not installed!"
            fi
        done
    fi
    touch /additional-apks-are-installed
fi

# Install additional php extensions
if [ -n "$ADDITIONAL_PHP_EXTENSIONS" ]; then
    if ! [ -f "/additional-php-extensions-are-installed" ]; then
        read -ra ADDITIONAL_PHP_EXTENSIONS_ARRAY <<< "$ADDITIONAL_PHP_EXTENSIONS"
        for app in "${ADDITIONAL_PHP_EXTENSIONS_ARRAY[@]}"; do
            if [ "$app" = imagick ]; then
                echo "Enabling Imagick..."
                if ! docker-php-ext-enable imagick >/dev/null; then
                    echo "Could not install PHP extension imagick!"
                fi
                continue
            fi
            # shellcheck disable=SC2086
            if [ "$PHP_DEPS_ARE_INSTALLED" != 1 ]; then
                echo "Installing PHP build dependencies..."
                    if ! apk add --no-cache --virtual .build-deps \
                        libxml2-dev \
                        autoconf \
                        $PHPIZE_DEPS >/dev/null; then
                    echo "Could not install build-deps!"
                fi
                PHP_DEPS_ARE_INSTALLED=1
            fi
            if [ "$app" = inotify ]; then
                echo "Installing $app via PECL..."
                pecl install "$app" >/dev/null
                if ! docker-php-ext-enable "$app" >/dev/null; then
                    echo "Could not install PHP extension $app!"
                fi
            elif [ "$app" = soap ]; then
                echo "Installing $app from core..."
                if ! docker-php-ext-install -j "$(nproc)" "$app" >/dev/null; then
                    echo "Could not install PHP extension $app!"
                fi
            else
                echo "Installing PHP extension $app ..."
                if ! docker-php-ext-install -j "$(nproc)" "$app" >/dev/null; then
                    echo "Could not install $app from core. Trying to install from PECL..."
                    pecl install "$app" >/dev/null
                    if ! docker-php-ext-enable "$app" >/dev/null; then
                        echo "Could also not install $app from PECL. The PHP extensions was not installed!"
                    fi
                fi
            fi
        done
        if [ "$PHP_DEPS_ARE_INSTALLED" = 1 ]; then
            rm -rf /tmp/pear
            runDeps="$( \
                scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions \
                    | tr ',' '\n' \
                    | sort -u \
                    | awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
            )";
            # shellcheck disable=SC2086
            apk add --virtual .nextcloud-phpext-rundeps $runDeps >/dev/null
            apk del .build-deps >/dev/null
        fi
    fi
    touch /additional-php-extensions-are-installed
fi

# Run original entrypoint
if ! sudo -E -u www-data bash /entrypoint.sh; then
    exit 1
fi

# Correctly set CPU_ARCH for notify_push
CPU_ARCH="$(uname -m)"
export CPU_ARCH
if [ -z "$CPU_ARCH" ]; then
    echo "Could not get processor architecture. Exiting."
    exit 1
elif [ "$CPU_ARCH" != "x86_64" ]; then
    export CPU_ARCH="aarch64"
fi

exec "$@"