#!/bin/bash

# Show wiki if vm.overcommit is disabled
if [ "$(sysctl -n vm.overcommit_memory)" != "1" ]; then
    echo "Memory overcommit is disabled but necessary for safe operation"
    echo "See https://github.com/nextcloud/all-in-one/discussions/1731 how to enable overcommit"
fi

# Run redis with a password if provided
echo "Redis has started"
if [ -n "$REDIS_HOST_PASSWORD" ]; then
    exec redis-server --requirepass "$REDIS_HOST_PASSWORD" --loglevel warning
else
    exec redis-server --loglevel warning
fi

exec "$@"
