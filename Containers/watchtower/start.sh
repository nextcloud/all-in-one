#!/bin/bash

# Check if socket is available and readable
if ! [ -a "/var/run/docker.sock" ]; then
    echo "Docker socket is not available. Cannot continue."
    exit 1
elif ! test -r /var/run/docker.sock; then
    echo "Trying to fix docker.sock permissions internally..."
    GROUP="$(stat -c '%g' /var/run/docker.sock)"
    groupadd -g "$GROUP" docker && \
    usermod -aG docker root
    if ! test -r /var/run/docker.sock; then
        echo "Docker socket is not readable by the root user. Cannot continue."
        exit 1
    fi
fi

if [ -n "$CONTAINER_TO_UPDATE" ]; then
    exec /watchtower --cleanup --run-once "$CONTAINER_TO_UPDATE"
else
    echo "'CONTAINER_TO_UPDATE' is not set. Cannot update anything."
    exit 1
fi


exec "$@"
