#!/bin/bash

# Check if PostgreSQL is accepting connections on the Unix socket
if ! pg_isready -h /var/run/postgresql -q 2>/dev/null; then
    exit 1
fi

# Check if Windmill is accepting connections on port 8000
if ! nc -z localhost 8000; then
    exit 1
fi
