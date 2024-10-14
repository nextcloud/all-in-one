<?php
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
$CONFIG = array (
  'apps_paths' => array (
      0 => array (
              'path'     => '/var/www/html/apps',
              'url'      => '/apps',
              'writable' => false,
      ),
      1 => array (
              'path'     => '/var/www/html/custom_apps',
              'url'      => '/custom_apps',
              'writable' => true,
      ),
  ),
);
if (getenv('APPS_ALLOWLIST')) {
    $CONFIG['appsallowlist'] = explode(" ", getenv('APPS_ALLOWLIST'));
}
