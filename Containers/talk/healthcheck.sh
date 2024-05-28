#!/bin/bash

nc -z 127.0.0.1 8081 || exit 1
nc -z 127.0.0.1 8188 || exit 1
nc -z 127.0.0.1 4222 || exit 1
nc -z 127.0.0.1 "$TALK_PORT" || exit 1
eturnalctl status || exit 1
if ! nc -z "$NC_DOMAIN" "$TALK_PORT"; then
    echo "Could not reach $NC_DOMAIN on port $TALK_PORT."
    exit 1
fi
