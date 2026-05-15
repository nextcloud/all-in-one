#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

# Defensive default: ensure AIO_LOG_LEVEL is never empty so log-level mappings below always resolve correctly
AIO_LOG_LEVEL="${AIO_LOG_LEVEL:-warn}"

ELASTIC_LOG_LEVEL="$(echo "$AIO_LOG_LEVEL" | tr '[:lower:]' '[:upper:]')"

exec env "logger.level=$ELASTIC_LOG_LEVEL" /usr/local/bin/docker-entrypoint.sh "$@"
