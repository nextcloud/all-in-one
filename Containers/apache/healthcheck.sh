#!/bin/bash

nc -z "$NEXTCLOUD_HOST" 9000 || exit 0
nc -z localhost 8000 || exit 1
nc -z localhost "$APACHE_PORT" || exit 1
nc -z "$NC_DOMAIN" 443 || exit 1
