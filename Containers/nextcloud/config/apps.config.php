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
);
if (getenv('APPS_ALLOWLIST')) {
    $CONFIG['appsallowlist'] = explode(" ", getenv('APPS_ALLOWLIST'));
}

$appStoreUrl = getenv('NEXTCLOUD_APP_STORE_URL');
if ($appStoreUrl) {
    if ($appStoreUrl === 'no') {
        $CONFIG['appstoreenabled '] = false;
    } else {
        $CONFIG['appstoreurl'] = getenv('NEXTCLOUD_APP_STORE_URL');
    }
}
