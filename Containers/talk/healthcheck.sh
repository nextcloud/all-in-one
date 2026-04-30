#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

nc -z 127.0.0.1 8081 || nc -z ::1 8081 || exit 1
nc -z 127.0.0.1 8188 || exit 1
nc -z 127.0.0.1 4222 || exit 1
nc -z 127.0.0.1 "$TALK_PORT" || nc -z ::1 "$TALK_PORT" || exit 1
eturnalctl status || exit 1
# Verify that the signaling server is actually serving requests, not just
# listening on the TCP port (which nc -z above only tests for open port).
# SC2102: [::1] is an IPv6 address literal in a URL, not a character-range glob.
# shellcheck disable=SC2102
wget -q -O /dev/null http://127.0.0.1:8081/api/v1/stats || wget -q -O /dev/null http://[::1]:8081/api/v1/stats || exit 1
