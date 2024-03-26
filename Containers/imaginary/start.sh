#!/bin/bash

echo "Imaginary has started"
if [ -z "$IMAGINARY_SECRET" ]; then
    imaginary -return-size -max-allowed-resolution 222.2 "$@"
else
    imaginary -return-size -max-allowed-resolution 222.2 -key "$IMAGINARY_SECRET" "$@"
fi
