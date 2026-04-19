#!/bin/bash

echo "Imaginary has started"

IMAGINARY_ARGS=(-return-size -max-allowed-resolution 222.2)

if [ -n "$IMAGINARY_SECRET" ]; then
    IMAGINARY_ARGS+=(-key "$IMAGINARY_SECRET")
fi

imaginary "${IMAGINARY_ARGS[@]}" "$@"
