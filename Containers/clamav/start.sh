#!/bin/bash

sed "s|aio-placeholder|$MAX_SIZE|" /etc/clamav/clamd.conf > /tmp/clamd.conf
cp /etc/clamav/clamav-milter.conf /tmp/clamv-milter-conf

# Print out clamav version for compliance reasons
clamscan --version

echo "Clamav started"

exec "$@"
