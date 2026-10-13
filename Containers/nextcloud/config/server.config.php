<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$CONFIG = array (
  'serverid' => hexdec(hash('xxh32', gethostname())) & 0x1FF,
);
