#!/bin/bash
# SPDX-FileCopyrightText: 2023 Nextcloud GmbH <https://nextcloud.com>
# SPDX-License-Identifier: AGPL-3.0-only


if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

if ! nc -z "$NEXTCLOUD_HOST" 9001; then
    exit 0
fi

nc -z 127.0.0.1 7867 || exit 1
