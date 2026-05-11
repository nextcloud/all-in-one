# SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

ELASTIC_LOG_LEVEL="$(echo "$AIO_LOG_LEVEL" | tr '[:lower:]' '[:upper:]')"

exec env "logger.level=$ELASTIC_LOG_LEVEL" /usr/local/bin/docker-entrypoint.sh "$@"
