#!/bin/bash

# Set a default value for POSTGRES_PORT
if [ -z "$POSTGRES_PORT" ]; then
    POSTGRES_PORT=5432
fi

# Only start container if database is accessible
# POSTGRES_HOST must be set in the containers env vars and POSTGRES_PORT has a default above
# shellcheck disable=SC2153
while ! sudo -E -u www-data nc -z "$POSTGRES_HOST" "$POSTGRES_PORT"; do
    echo "Waiting for database to start..."
    sleep 5
done

# Use the correct Postgres username
POSTGRES_USER="oc_$POSTGRES_USER"
export POSTGRES_USER

# Check that db type is not empty
if [ -z "$DATABASE_TYPE" ]; then
    export DATABASE_TYPE=postgres
fi

# Fix false database connection on old instances
if [ -f "/var/www/html/config/config.php" ]; then
    sleep 2
    while ! sudo -E -u www-data psql -d "postgresql://$POSTGRES_USER:$POSTGRES_PASSWORD@$POSTGRES_HOST:$POSTGRES_PORT/$POSTGRES_DB" -c "select now()"; do
        echo "Waiting for the database to start..."
        sleep 5
    done
    if [ "$POSTGRES_USER" = "oc_nextcloud" ] && [ "$POSTGRES_DB" = "nextcloud_database" ] && echo "$POSTGRES_PASSWORD" | grep -q '^[a-z0-9]\+$'; then
        # This was introduced with https://github.com/nextcloud/all-in-one/pull/218
        sed -i "s|'dbuser'.*=>.*$|'dbuser' => '$POSTGRES_USER',|" /var/www/html/config/config.php
        sed -i "s|'dbpassword'.*=>.*$|'dbpassword' => '$POSTGRES_PASSWORD',|" /var/www/html/config/config.php
        sed -i "s|'db_name'.*=>.*$|'db_name' => '$POSTGRES_DB',|" /var/www/html/config/config.php
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
    # From https://memories.gallery/hw-transcoding/#docker-installations
    GID="$(stat -c "%g" /dev/dri/renderD128)"
    groupadd -g "$GID" render2 || true # sometimes this is needed
    GROUP="$(getent group "$GID" | cut -d: -f1)"
    usermod -aG "$GROUP" www-data
    touch "/dev-dri-group-was-added"
fi
set +x

# Check datadir permissions
sudo -E -u www-data touch "$NEXTCLOUD_DATA_DIR/this-is-a-test-file" &>/dev/null
if ! [ -f "$NEXTCLOUD_DATA_DIR/this-is-a-test-file" ]; then
    chown -R www-data:root "$NEXTCLOUD_DATA_DIR"
    chmod 750 -R "$NEXTCLOUD_DATA_DIR"
fi
sudo -E -u www-data rm -f "$NEXTCLOUD_DATA_DIR/this-is-a-test-file"

# Install additional dependencies
if [ -n "$ADDITIONAL_APKS" ]; then
    if ! [ -f "/additional-apks-are-installed" ]; then
        # Allow to disable imagemagick without having to download it each time
        if ! echo "$ADDITIONAL_APKS" | grep -q imagemagick; then
            apk del imagemagick imagemagick-svg imagemagick-heic imagemagick-tiff;
        fi
        read -ra ADDITIONAL_APKS_ARRAY <<< "$ADDITIONAL_APKS"
        for app in "${ADDITIONAL_APKS_ARRAY[@]}"; do
            if [ "$app" != imagemagick ]; then
                echo "Installing $app via apk..."
                if ! apk add --no-cache "$app" >/dev/null; then
                    echo "The packet $app was not installed!"
                fi
            fi
        done
    fi
    touch /additional-apks-are-installed
fi

# Install additional php extensions
if [ -n "$ADDITIONAL_PHP_EXTENSIONS" ]; then
    if ! [ -f "/additional-php-extensions-are-installed" ]; then
        # Allow to disable imagick without having to enable it each time
        if ! echo "$ADDITIONAL_PHP_EXTENSIONS" | grep -q imagick; then
            # Remove the ini file as there is no docker-php-ext-disable script available
            rm /usr/local/etc/php/conf.d/docker-php-ext-imagick.ini
        fi
        read -ra ADDITIONAL_PHP_EXTENSIONS_ARRAY <<< "$ADDITIONAL_PHP_EXTENSIONS"
        for app in "${ADDITIONAL_PHP_EXTENSIONS_ARRAY[@]}"; do
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
            apk add --no-cache --virtual .nextcloud-phpext-rundeps $runDeps >/dev/null
            apk del .build-deps >/dev/null
        fi
    fi
    touch /additional-php-extensions-are-installed
fi

# Run original entrypoint
if ! sudo -E -u www-data bash /entrypoint.sh; then
    exit 1
fi

while [ "$THIS_IS_AIO" = "true" ] && [ -z "$(dig nextcloud-aio-apache A +short +search)" ]; do
    echo "Waiting for nextcloud-aio-apache to start..."
    sleep 5
done

set -x
# shellcheck disable=SC2235
if [ "$THIS_IS_AIO" = "true" ] && [ "$APACHE_PORT" = 443 ]; then
    IPv4_ADDRESS_APACHE="$(dig nextcloud-aio-apache A +short +search | grep '^[0-9.]\+$' | sort | head -n1)"
    IPv6_ADDRESS_APACHE="$(dig nextcloud-aio-apache AAAA +short +search | grep '^[0-9a-f:]\+$' | sort | head -n1)"
    IPv4_ADDRESS_MASTERCONTAINER="$(dig nextcloud-aio-mastercontainer A +short +search | grep '^[0-9.]\+$' | sort | head -n1)"
    IPv6_ADDRESS_MASTERCONTAINER="$(dig nextcloud-aio-mastercontainer AAAA +short +search | grep '^[0-9a-f:]\+$' | sort | head -n1)"

    sed -i "s|^;listen.allowed_clients|listen.allowed_clients|" /usr/local/etc/php-fpm.d/www.conf
    sed -i "s|listen.allowed_clients.*|listen.allowed_clients = 127.0.0.1,::1,$IPv4_ADDRESS_APACHE,$IPv6_ADDRESS_APACHE,$IPv4_ADDRESS_MASTERCONTAINER,$IPv6_ADDRESS_MASTERCONTAINER|" /usr/local/etc/php-fpm.d/www.conf
    sed -i "/^listen.allowed_clients/s/,,/,/g" /usr/local/etc/php-fpm.d/www.conf
    sed -i "/^listen.allowed_clients/s/,$//" /usr/local/etc/php-fpm.d/www.conf
    grep listen.allowed_clients /usr/local/etc/php-fpm.d/www.conf
fi
set +x

exec "$@"
