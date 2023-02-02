#!/bin/bash

# Only start container if database is accessible (needed for backup to work correctly)
while ! nc -z "$POSTGRES_HOST" 5432; do
    echo "Waiting for database to start..."
    sleep 5
done

# Show wiki if vm.max_map_count is too low
if [ "$(sysctl -n vm.max_map_count)" -le 65530 ]; then
    echo "max_map_count is too low and needs to be adjusted."
    echo "See https://github.com/nextcloud/all-in-one/discussions/1775 how to change max_map_count"
fi

# Run initial entrypoint
/bin/tini -- /usr/local/bin/docker-entrypoint.sh

exec "$@"
