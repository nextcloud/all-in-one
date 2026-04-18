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
if [ -z "$IMAGINARY_SECRET" ]; then
    imaginary -return-size -max-allowed-resolution 222.2 "$@"
else
    imaginary -return-size -max-allowed-resolution 222.2 -key "$IMAGINARY_SECRET" "$@"
fi
