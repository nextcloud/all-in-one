#!/bin/bash

nc -z "$NEXTCLOUD_HOST" 9001 || exit 0
nc -z 127.0.0.1 2375 || exit 1
