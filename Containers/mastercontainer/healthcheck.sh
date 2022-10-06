#!/bin/bash

if [ -f "/mnt/docker-aio-config/data/configuration.json" ]; then
    nc -z localhost 8080 || exit 1
fi
