<?php
if (getenv('NEXTCLOUD_TRUSTED_CERTIFICATES_POSTGRES')) {
  $CONFIG = array(
    'pgsql_ssl' => array(
      'mode' => 'verify-ca',
      'rootcert' => '/var/www/html/data/certificates/POSTGRES',
    ),
  );
}
