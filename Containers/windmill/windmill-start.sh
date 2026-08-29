#!/bin/bash

# Wait for PostgreSQL to accept connections
until pg_isready -h /var/run/postgresql -q 2>/dev/null; do
    echo "Waiting for PostgreSQL to be ready..."
    sleep 2
done

# Start Windmill
exec windmill
