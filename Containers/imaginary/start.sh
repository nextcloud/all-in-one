#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    export DEBUG='*'
    export GOLANG_LOG='info'
elif [ "$AIO_LOG_LEVEL" = 'info' ]; then
    export GOLANG_LOG='info'
elif [ "$AIO_LOG_LEVEL" = 'warn' ]; then
    export GOLANG_LOG='warning'
else
    export GOLANG_LOG='error'
fi

echo "Imaginary has started"

IMAGINARY_ARGS=(-return-size -max-allowed-resolution 222.2)

if [ -n "$IMAGINARY_SECRET" ]; then
    IMAGINARY_ARGS+=(-key "$IMAGINARY_SECRET")
fi

exec imaginary "${IMAGINARY_ARGS[@]}" "$@"
