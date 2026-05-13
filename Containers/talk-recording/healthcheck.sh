#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

nc -z 127.0.0.1 1234 || exit 1
