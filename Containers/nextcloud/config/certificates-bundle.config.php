<?php
// Check if NEXTCLOUD_TRUSTED_CERTIFICATES_ are configured
if (str_contains(implode(' ', array_keys(getenv())), 'NEXTCLOUD_TRUSTED_CERTIFICATES_')) {
  $CONFIG['default_certificates_bundle_path'] = '/var/www/html/data/certificates/ca-bundle.crt';
}
