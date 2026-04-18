#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

if [ "$AIO_LOG_LEVEL" = "warn" ]; then
    REDIS_LOG_LEVEL="warning"
else
    REDIS_LOG_LEVEL="$AIO_LOG_LEVEL"
fi
export REDIS_LOG_LEVEL

# Show wiki if vm.overcommit is disabled
if [ "$(sysctl -n vm.overcommit_memory)" != "1" ]; then
    echo "Memory overcommit is disabled but necessary for safe operation"
    echo "See https://github.com/nextcloud/all-in-one/discussions/1731 how to enable overcommit"
fi

# Run redis with a password if provided
echo "Redis has started"
if [ -n "$REDIS_HOST_PASSWORD" ]; then
    exec redis-server --requirepass "$REDIS_HOST_PASSWORD" --loglevel "$REDIS_LOG_LEVEL"
else
    exec redis-server --loglevel "$REDIS_LOG_LEVEL"
fi

exec "$@"
