#!/bin/bash

if [ -f "/mnt/docker-aio-config/data/configuration.json" ]; then
    nc -z 127.0.0.1 80 || exit 1
    nc -z 127.0.0.1 8000 || exit 1
    nc -z 127.0.0.1 8080 || exit 1
    nc -z 127.0.0.1 8443 || exit 1
    nc -z 127.0.0.1 9000 || exit 1
    nc -z 127.0.0.1 9876 || exit 1
fi
