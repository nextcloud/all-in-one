<?php
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
  'appsallowlist' => getenv('APPS_ALLOWLIST') ? explode(" ", getenv('APPS_ALLOWLIST')) : false,
);
