#!/usr/bin/env sh

if [ "$(echo "PING" | nc 127.0.0.1 3310)" != "PONG" ]; then
	echo "ERROR: Unable to contact server"
	exit 1
fi

echo "Clamd is up"
exit 0
