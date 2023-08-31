#!/bin/bash

nc -z "$NEXTCLOUD_HOST" 9000 || exit 0
if ! nc -z localhost 2375; then
    exit 1
fi
