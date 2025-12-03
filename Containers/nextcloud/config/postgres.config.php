<?php
if (getenv('NEXTCLOUD_TRUSTED_CERTIFICATES_POSTGRES')) {
  $CONFIG = array(
    'pgsql_ssl' => array(
      'mode' => 'verify-ca',
      'rootcert' => '/var/www/html/data/certificates/POSTGRES',
    ),
  );
}
if (getenv('NEXTCLOUD_TRUSTED_CERTIFICATES_MYSQL')) {
  $CONFIG = array(
    'dbdriveroptions' => array(
      'PDO::MYSQL_ATTR_SSL_CA' => '/var/www/html/data/certificates/MYSQL',
    ),
  );
}

