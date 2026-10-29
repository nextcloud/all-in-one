#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

# Always log on info level so that it is visible what imaginary does
echo "Note: This service is pinned to a log level of at least 'info' in order to always show some information, because it makes catching errors easier."
export GOLANG_LOG=info
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
