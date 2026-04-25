#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

GOLANG_LOG="$(case "$AIO_LOG_LEVEL" in
    debug) printf 'info' ;;
    info) printf 'info' ;;
    warn) printf 'warning' ;;
    error) printf 'error' ;;
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
