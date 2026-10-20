#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

GOLANG_LOG="$(case "$AIO_LOG_LEVEL" in
    debug) printf 'info' ;;
    info) printf 'info' ;;
    warn) printf 'warning' ;;
    error) printf 'error' ;;
esac)"
export GOLANG_LOG
if [ "$AIO_LOG_LEVEL" = "debug" ]; then
    export DEBUG='*'
fi

if [ "$AIO_LOG_LEVEL" = "debug" ] || [ "$AIO_LOG_LEVEL" = "info" ]; then
    export ACCESS_LOG=1
fi

if [ -z "$IMAGINARY_SECRET" ]; then
    echo "You need to provide the IMAGINARY_SECRET."
    exit 1
fi

# Forward additional arguments to imaginary
export IMAGINARY_ADDITIONAL_ARGS="$*"

echo "Imaginary has started"

exec dinit --system --container imaginary ffmpeg-preview-service
