#!/bin/bash

# Run redis with a password if provided
if [ -n "$REDIS_HOST_PASSWORD" ]; then
    exec redis-server --requirepass "$REDIS_HOST_PASSWORD"
else
    exec redis-server
fi

exec "$@"
