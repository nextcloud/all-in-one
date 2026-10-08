#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

# Always log on info level so that it is visible what imaginary does
export GOLANG_LOG=info
if [ "$AIO_LOG_LEVEL" = "debug" ]; then
    export DEBUG='*'
fi

echo "Imaginary has started"

IMAGINARY_ARGS=(-return-size -max-allowed-resolution 222.2)

if [ -n "$IMAGINARY_SECRET" ]; then
    IMAGINARY_ARGS+=(-key "$IMAGINARY_SECRET")
fi

exec imaginary "${IMAGINARY_ARGS[@]}" "$@"
