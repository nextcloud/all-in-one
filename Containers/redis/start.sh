#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

# Redis only supports [debug, verbose, notice, warning, nothing] as log level
if [ "$AIO_LOG_LEVEL" = "warn" ] || [ "$AIO_LOG_LEVEL" = "error" ]; then
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

# Warn if Transparent Huge Pages are enabled (causes latency spikes)
if [ -f /sys/kernel/mm/transparent_hugepage/enabled ]; then
    if grep -q '\[always\]' /sys/kernel/mm/transparent_hugepage/enabled; then
        echo "WARNING: Transparent Huge Pages (THP) are enabled. This can cause latency and memory issues with Redis."
        echo "Consider disabling THP by running: echo never > /sys/kernel/mm/transparent_hugepage/enabled"
    fi
fi

# Build the redis-server argument list.
REDIS_ARGS=(
    --loglevel "$REDIS_LOG_LEVEL"
    --save "" # Disable RDB persistence (Redis is used as a pure cache/lock store)
    --maxmemory-policy allkeys-lru # Evict least-recently-used keys when memory is full
    --lazyfree-lazy-eviction yes # Perform evictions in a background thread
    --lazyfree-lazy-expire yes # Expire keys in a background thread
    --lazyfree-lazy-server-del yes # DEL/UNLINK in background thread
    --replica-lazy-flush yes # Flush replica dataset in background thread
    --activedefrag yes # Reclaim fragmented memory without restart
    --hz 15 # Run background tasks 15×/s (default 10) for faster key expiry
)

if [ -n "$REDIS_HOST_PASSWORD" ]; then
    REDIS_ARGS+=(--requirepass "$REDIS_HOST_PASSWORD")
fi

# Run redis with a password if provided
echo "Redis has started"
exec redis-server "${REDIS_ARGS[@]}"
