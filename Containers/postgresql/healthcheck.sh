#!/bin/bash
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

test -f "/mnt/data/backup-is-running" && exit 0

psql -d "postgresql://oc_$POSTGRES_USER:$POSTGRES_PASSWORD@127.0.0.1:5432/$POSTGRES_DB" -c "select now()" || exit 1
