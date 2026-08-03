#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
	set -x
fi

# Print out clamav version for compliance reasons
clamscan --version

echo "Clamav started"

exec "$@"
