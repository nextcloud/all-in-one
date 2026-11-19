#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

nc -z 127.0.0.1 80 || exit 1
nc -z 127.0.0.1 8000 || exit 1
