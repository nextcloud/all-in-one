#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

if [ "$AIO_LOG_LEVEL" = "warn" ]; then
    COLLABORA_LOG_LEVEL="warning"
elif [ "$AIO_LOG_LEVEL" = "info" ]; then
    COLLABORA_LOG_LEVEL="notice"
else
    COLLABORA_LOG_LEVEL="$AIO_LOG_LEVEL"
fi

# Replace the hardcoded log level in extra_params with the translated one
extra_params+=" --o:logging.level=$COLLABORA_LOG_LEVEL --o:logging.level_startup=$COLLABORA_LOG_LEVEL"
export extra_params

exec /start-collabora-online.sh "$@"
