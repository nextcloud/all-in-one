#!/bin/bash
# SPDX-FileCopyrightText: 2022 Nextcloud GmbH <https://nextcloud.com>
# SPDX-License-Identifier: AGPL-3.0-only


if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

if [ -f "/mnt/docker-aio-config/data/configuration.json" ]; then
    nc -z 127.0.0.1 80 || exit 1
    nc -z 127.0.0.1 8080 || exit 1
    nc -z 127.0.0.1 8443 || exit 1
    test -S /run/php.sock || exit 1
    nc -z 127.0.0.1 9876 || exit 1
fi
