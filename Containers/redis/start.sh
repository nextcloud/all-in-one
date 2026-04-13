#!/bin/bash

# Show wiki if vm.overcommit is disabled
if [ "$(sysctl -n vm.overcommit_memory)" != "1" ]; then
    echo "Memory overcommit is disabled but necessary for safe operation"
    echo "See https://github.com/nextcloud/all-in-one/discussions/1731 how to enable overcommit"
fi

# Map AIO_LOG_LEVEL to Redis log level
case "${AIO_LOG_LEVEL:-warning}" in
    debug)   REDIS_LOG_LEVEL="debug" ;;
    info)    REDIS_LOG_LEVEL="verbose" ;;
    warning) REDIS_LOG_LEVEL="notice" ;;
    error)   REDIS_LOG_LEVEL="warning" ;;
    *)       REDIS_LOG_LEVEL="notice" ;;
esac

# Run redis with a password if provided
echo "Redis has started"
if [ -n "$REDIS_HOST_PASSWORD" ]; then
    exec redis-server --requirepass "$REDIS_HOST_PASSWORD" --loglevel "$REDIS_LOG_LEVEL"
else
    exec redis-server --loglevel "$REDIS_LOG_LEVEL"
fi

exec "$@"
