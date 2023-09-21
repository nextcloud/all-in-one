#!/bin/bash

if [ -f "/mnt/docker-aio-config/data/configuration.json" ]; then
    nc -z localhost 80 || exit 1
    nc -z localhost 8000 || exit 1
    nc -z localhost 8080 || exit 1
    nc -z localhost 8443 || exit 1
    nc -z localhost 9000 || exit 1
    nc -z localhost 9876 || exit 1
fi
