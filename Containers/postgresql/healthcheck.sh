#!/bin/bash

test -f "/mnt/data/backup-is-running" && exit 0

POSTGRES_PORT=11000 /usr/local/bin/aio-pg-healthcheck debug || exec /usr/local/bin/aio-pg-healthcheck
