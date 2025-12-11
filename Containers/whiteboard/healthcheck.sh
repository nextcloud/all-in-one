#!/bin/bash

nc -z "$REDIS_HOST" "$REDIS_PORT" || exit 0
nc -z 127.0.0.1 3002 || exit 1
