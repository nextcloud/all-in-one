#!/bin/bash

# Check if Windmill is accepting connections on port 8000
if ! nc -z localhost 8000; then
    exit 1
fi
