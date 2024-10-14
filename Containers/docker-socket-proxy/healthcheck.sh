#!/bin/bash
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

nc -z "$NEXTCLOUD_HOST" 9001 || exit 0
nc -z 127.0.0.1 2375 || exit 1
