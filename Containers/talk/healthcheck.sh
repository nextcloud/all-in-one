#!/bin/bash

nc -z 127.0.0.1 8081 || exit 1
nc -z 127.0.0.1 8188 || exit 1
nc -z 127.0.0.1 4222 || exit 1
nc -z 127.0.0.1 "$TALK_PORT" || exit 1
eturnalctl status || exit 1
# Verify that the signaling server is actually serving requests, not just
# listening on the TCP port (which nc -z above only tests for open port).
wget -q -O /dev/null http://127.0.0.1:8081/api/v1/stats || exit 1
