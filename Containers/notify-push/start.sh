#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

export RUST_LOG="$AIO_LOG_LEVEL"

if [ -z "$NEXTCLOUD_HOST" ]; then
    echo "NEXTCLOUD_HOST needs to be provided. Exiting!"
    exit 1
fi

# Only start container if nextcloud is accessible
while ! nc -z "$NEXTCLOUD_HOST" 9001; do
    echo "Waiting for Nextcloud to start..."
    sleep 5
done

# Correctly set CPU_ARCH for notify_push
CPU_ARCH="$(uname -m)"
export CPU_ARCH
if [ -z "$CPU_ARCH" ]; then
    echo "Could not get processor architecture. Exiting."
    exit 1
elif [ "$CPU_ARCH" != "x86_64" ]; then
    export CPU_ARCH="aarch64"
fi

# Add warning
if ! [ -f /var/www/html/custom_apps/notify_push/bin/"$CPU_ARCH"/notify_push ] && ! [ -f /var/www/html/apps/notify_push/bin/"$CPU_ARCH"/notify_push ]; then
    echo "The notify_push binary was not found."
    echo "Most likely is DNS resolution not working correctly."
    echo "You can try to fix this by configuring a DNS server globally in dockers daemon.json."
    echo "See https://dockerlabs.collabnix.com/intermediate/networking/Configuring_DNS.html"
    echo "Afterwards a restart of docker should automatically resolve this."
    echo "Additionally, make sure to disable VPN software that might be running on your server"
    echo "Also check your firewall if it blocks connections to github"
    echo "If it should still not work afterwards, feel free to create a new thread at https://github.com/nextcloud/all-in-one/discussions/new?category=questions and post the Nextcloud container logs there."
    echo ""
    echo ""
    exit 1
fi

# Logic for ipv6 disabled servers
BIND="::"
if grep -q "1" /sys/module/ipv6/parameters/disable \
|| grep -q "1" /proc/sys/net/ipv6/conf/all/disable_ipv6 \
|| grep -q "1" /proc/sys/net/ipv6/conf/default/disable_ipv6; then
    BIND="0.0.0.0"
fi
export BIND

echo "notify-push was started"


if [ -f /var/www/html/custom_apps/notify_push/bin/"$CPU_ARCH"/notify_push ]; then
    PUSH_PATH="/var/www/html/custom_apps/notify_push/bin/$CPU_ARCH/notify_push"
else
    PUSH_PATH="/var/www/html/apps/notify_push/bin/$CPU_ARCH/notify_push"
fi
# Run it
exec "$PUSH_PATH" \
    --port 7867 \
    /var/www/html/config/config.php
