#!/bin/bash
# SPDX-FileCopyrightText: 2025 Nextcloud GmbH <https://nextcloud.com>
# SPDX-License-Identifier: AGPL-3.0-only


if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

if [ "$(echo "PING" | nc 127.0.0.1 3310)" != "PONG" ]; then
	echo "ERROR: Unable to contact server"
	exit 1
fi

echo "Clamd is up"
exit 0
