#!/bin/bash

# Check if socket is available and readable
if ! [ -e "/var/run/docker.sock" ]; then
    echo "Docker socket is not available. Cannot continue."
    exit 1
elif ! test -r /var/run/docker.sock; then
    echo "Docker socket is not readable by the root user. Cannot continue."
    exit 1
fi

if [ -f /run/.containerenv ]; then
    # If running under podman disable memory_swappiness setting in watchtower.
    # It is a necessary workaround until https://github.com/containers/podman/issues/23824 gets fixed.
    echo "Running under Podman. Setting WATCHTOWER_DISABLE_MEMORY_SWAPPINESS to 1."
    export WATCHTOWER_DISABLE_MEMORY_SWAPPINESS=1
fi

if [ -n "$CONTAINER_TO_UPDATE" ]; then
    exec /watchtower --cleanup --debug --run-once "$CONTAINER_TO_UPDATE"
else
    echo "'CONTAINER_TO_UPDATE' is not set. Cannot update anything."
    exit 1
fi

exec "$@"
