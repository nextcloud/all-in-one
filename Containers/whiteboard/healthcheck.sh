#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

nc -z "$REDIS_HOST" "$REDIS_PORT" || exit 0
nc -z 127.0.0.1 3002 || exit 1
