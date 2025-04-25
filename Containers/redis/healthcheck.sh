#!/bin/bash

redis-cli -a "$REDIS_HOST_PASSWORD" PING || exit 1
