#!/bin/bash

# Wrapper used by dinit to start caddy, so `dinitctl restart` makes the process pick up changed
# values (dinit services otherwise keep the environment from when dinit itself was started).

if [ -f "$CADDY_ENV_FILE" ]; then
    . "$CADDY_ENV_FILE"
fi
export AIO_SUBNETS

exec /usr/bin/caddy run --config "$1"
