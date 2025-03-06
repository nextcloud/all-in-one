#!/usr/bin/env sh

sed "s|aio-placeholder|$MAX_SIZE|" /etc/clamav/clamd.conf > /tmp/clamd.conf

exec "$@"
