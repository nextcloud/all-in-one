# SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

curl -fs "http://127.0.0.1:9200/_cluster/health?filter_path=status" | grep -qE '"status":"(green|yellow)"' || exit 1
