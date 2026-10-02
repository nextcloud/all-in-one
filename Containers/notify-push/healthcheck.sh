#!/bin/bash

# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

if ! nc -z "$NEXTCLOUD_HOST" 9001; then
    exit 0
fi

nc -z 127.0.0.1 7867 || exit 1
