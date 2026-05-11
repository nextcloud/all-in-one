# SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

#!/bin/bash

# Unfortunately, no curl and no nc is installed in the container 
# and packages can also not be added as the package list is broken.
# So always exiting 0 for now.
# nc http://127.0.0.1:9980 || exit 1
exit 0
