#!/bin/bash

sed "s|aio-placeholder|$MAX_SIZE|" /etc/clamav/clamd.conf > /tmp/clamd.conf

if [ "${STALWART}" ]; then
  cp /etc/clamav/clamav-milter.conf /tmp/clamv-milter-conf
fi

# Print out clamav version for compliance reasons
clamscan --version

echo "Clamav started"

exec "$@"
