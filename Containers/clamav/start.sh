#!/usr/bin/env sh

if ! freshclam --foreground --stdout; then
  exit 1
fi

sed "s|aio-placeholder|$MAX_SIZE|" /etc/clamav/clamd.conf > /tmp/clamd.conf

freshclam --foreground --stdout --daemon & 
clamd --foreground --config-file=/tmp/clamd.conf
