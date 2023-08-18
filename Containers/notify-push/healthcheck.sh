#!/bin/bash

if ! nc -z "$NEXTCLOUD_HOST" 9000; then
    exit 0
fi

nc -z localhost 7867 || exit 1
