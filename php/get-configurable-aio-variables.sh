#!/usr/bin/env bash

# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

awk '/^    public [^f][^u][^n]/ { sub(/\$/, "", $3); print $3 }' src/Data/ConfigurationManager.php | sort
