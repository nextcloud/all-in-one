#!/bin/bash

sed "s|aio-placeholder|$MAX_SIZE|" /etc/clamav/clamd.conf > /tmp/clamd.conf

echo "Clamav started"

exec "$@"
