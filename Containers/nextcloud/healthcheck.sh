#!/bin/bash

nc -z "$POSTGRES_HOST" 5432 || exit 0

if ! nc -z localhost 9000 || ! nc -z localhost 7867; then
    exit 1
fi
