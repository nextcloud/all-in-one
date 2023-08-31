#!/bin/bash

nc -z "$NEXTCLOUD_HOST" 9000 || exit 0
nc -z localhost 2375 || exit 1
