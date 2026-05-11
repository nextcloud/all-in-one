#!/bin/bash
# SPDX-FileCopyrightText: 2023 Nextcloud GmbH <https://nextcloud.com>
# SPDX-License-Identifier: AGPL-3.0-only


if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

# Set a default value for POSTGRES_PORT
if [ -z "$POSTGRES_PORT" ]; then
    POSTGRES_PORT=5432
fi


# POSTGRES_HOST must be set in the containers env vars and POSTGRES_PORT has a default above
# shellcheck disable=SC2153
nc -z "$POSTGRES_HOST" "$POSTGRES_PORT" || exit 0

if ! nc -z 127.0.0.1 9000; then
    exit 1
fi
