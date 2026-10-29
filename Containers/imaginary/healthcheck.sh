#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

nc -z 127.0.0.1 "$PORT" || exit 1
nc -z 127.0.0.1 "$FFMPEG_PREVIEW_PORT" || exit 1
