#!/bin/bash

# Adjust settings
cat /etc/clamav/clamd.conf > /tmp/clamd.conf
CLAMAV_FILE="$(sed "s|10G|$MAX_SIZE|" /clamav.conf)"
echo "$CLAMAV_FILE" >> /tmp/clamd.conf

# Call initial init
# shellcheck disable=SC2093
exec /init --config-file="/tmp/clamd.conf"

exec "$@"
