#!/bin/bash

sudo -u www-data nc -z "$POSTGRES_HOST" 5432 || exit 0

if ! sudo -u www-data nc -z localhost 9000 || ! sudo -u www-data nc -z localhost 7867; then
    exit 1
fi
