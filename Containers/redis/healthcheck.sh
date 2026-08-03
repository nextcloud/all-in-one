#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

redis-cli -a "$REDIS_HOST_PASSWORD" PING || exit 1
