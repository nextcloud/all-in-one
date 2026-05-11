#!/bin/bash
# SPDX-FileCopyrightText: 2023 Nextcloud GmbH <https://nextcloud.com>
# SPDX-License-Identifier: AGPL-3.0-only


if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

ELASTIC_LOG_LEVEL="$(echo "$AIO_LOG_LEVEL" | tr '[:lower:]' '[:upper:]')"

exec env "logger.level=$ELASTIC_LOG_LEVEL" /usr/local/bin/docker-entrypoint.sh "$@"
