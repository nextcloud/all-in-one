<?php
// SPDX-FileCopyrightText: 2025 Nextcloud GmbH <https://nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-only

if (getenv('NEXTCLOUD_TRUSTED_CERTIFICATES_POSTGRES')) {
  $CONFIG = array(
    'pgsql_ssl' => array(
      'mode' => 'verify-ca',
      'rootcert' => '/var/www/html/data/certificates/ca-bundle.crt',
    ),
  );
}
if (getenv('NEXTCLOUD_TRUSTED_CERTIFICATES_MYSQL')) {
  $CONFIG = array(
    'dbdriveroptions' => array(
      PDO::MYSQL_ATTR_SSL_CA => '/var/www/html/data/certificates/ca-bundle.crt',
    ),
  );
}

