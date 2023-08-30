#!/bin/bash

nc -z "$NEXTCLOUD_HOST" 9000 || exit 0
if [ "$(wget http://127.0.0.1:2375/v1.41/_ping -qO -)" != "OK" ]; then
    exit 1
fi
