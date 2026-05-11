#!/bin/bash
# SPDX-FileCopyrightText: 2024 Nextcloud GmbH <https://nextcloud.com>
# SPDX-License-Identifier: AGPL-3.0-only


if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
	set -x
fi

# Print out clamav version for compliance reasons
clamscan --version

echo "Clamav started"

exec "$@"
