#!/bin/bash

# Only start container if nextcloud is accessible
while ! nc -z "$REDIS_HOST" 6379; do
    echo "Waiting for redis to start..."
    sleep 5
done

# Set a default for redis db index
if [ -z "$REDIS_DB_INDEX" ]; then
    REDIS_DB_INDEX=0
fi

export REDIS_URL="redis://$REDIS_USER:$REDIS_HOST_PASSWORD@$REDIS_HOST/$REDIS_DB_INDEX"

# Run it
exec npm --prefix /app run server:start
