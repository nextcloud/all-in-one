#!/usr/bin/env sh

sed "s|aio-placeholder|$MAX_SIZE|" /etc/clamav/clamd.conf > /tmp/clamd.conf

freshclam --foreground --stdout --daemon & 
clamd --foreground --config-file=/tmp/clamd.conf
