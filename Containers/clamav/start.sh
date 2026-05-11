# SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
	set -x
fi

# Print out clamav version for compliance reasons
clamscan --version

echo "Clamav started"

exec "$@"
