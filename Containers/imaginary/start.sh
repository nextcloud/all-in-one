#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

# Defensive default: ensure AIO_LOG_LEVEL is never empty so log-level mappings below always resolve correctly
AIO_LOG_LEVEL="${AIO_LOG_LEVEL:-warn}"

GOLANG_LOG="$(case "$AIO_LOG_LEVEL" in
    debug) printf 'info' ;;
    info) printf 'info' ;;
    warn) printf 'warning' ;;
    error) printf 'error' ;;
    *) printf 'warning' ;;
esac)"
export GOLANG_LOG
if [ "$AIO_LOG_LEVEL" = "debug" ]; then
    export DEBUG='*'
fi

echo "Imaginary has started"

IMAGINARY_ARGS=(-return-size -max-allowed-resolution 222.2)

if [ -n "$IMAGINARY_SECRET" ]; then
    IMAGINARY_ARGS+=(-key "$IMAGINARY_SECRET")
fi

exec imaginary "${IMAGINARY_ARGS[@]}" "$@"
