#!/bin/bash
# SPDX-FileCopyrightText: 2022 Nextcloud GmbH <https://nextcloud.com>
# SPDX-License-Identifier: AGPL-3.0-only


if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

nc -z "$NEXTCLOUD_HOST" 9000 || exit 0
nc -z 127.0.0.1 8000 || exit 1
nc -z 127.0.0.1 "$APACHE_PORT" || exit 1
