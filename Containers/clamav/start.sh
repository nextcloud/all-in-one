#!/bin/bash

# Print out clamav version for compliance reasons
clamscan --version

echo "Clamav started"

exec "$@"
