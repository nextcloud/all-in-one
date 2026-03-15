<?php
if (getenv('OBJECTSTORE_S3_BUCKET')) {
  $use_ssl = getenv('OBJECTSTORE_S3_SSL');
  $use_path = getenv('OBJECTSTORE_S3_USEPATH_STYLE');
  $use_legacyauth = getenv('OBJECTSTORE_S3_LEGACYAUTH');
  $autocreate = getenv('OBJECTSTORE_S3_AUTOCREATE');
  $multibucket = getenv('OBJECTSTORE_S3_MULTIBUCKET');
  $CONFIG = array(
    'objectstore' => array(
      'class' => '\OC\Files\ObjectStore\S3',
      'arguments' => array(
        'multibucket' => $multibucket === 'true',
        'num_buckets' => (int)getenv('OBJECTSTORE_S3_NUM_BUCKETS') ?: 64,
        'bucket' => getenv('OBJECTSTORE_S3_BUCKET'),
        'key' => getenv('OBJECTSTORE_S3_KEY') ?: '',
        'secret' => getenv('OBJECTSTORE_S3_SECRET') ?: '',
        'region' => getenv('OBJECTSTORE_S3_REGION') ?: '',
        'hostname' => getenv('OBJECTSTORE_S3_HOST') ?: '',
        'port' => getenv('OBJECTSTORE_S3_PORT') ?: '',
        'storageClass' => getenv('OBJECTSTORE_S3_STORAGE_CLASS') ?: '',
        'objectPrefix' => getenv("OBJECTSTORE_S3_OBJECT_PREFIX") ? getenv("OBJECTSTORE_S3_OBJECT_PREFIX") : "urn:oid:",
        'autocreate' => strtolower($autocreate) !== 'false',
        'use_ssl' => strtolower($use_ssl) !== 'false',
        // required for some non Amazon S3 implementations
        'use_path_style' => strtolower($use_path) === 'true',
        // required for older protocol versions
        'legacy_auth' => strtolower($use_legacyauth) === 'true',
        'use_nextcloud_bundle' => 1,
      )
    )
  );

  $sse_c_key = getenv('OBJECTSTORE_S3_SSE_C_KEY');
  if ($sse_c_key) {
    $CONFIG['objectstore']['arguments']['sse_c_key'] = $sse_c_key;
  }

  $requestChecksumValidation = getenv('OBJECTSTORE_S3_REQUEST_CHECKSUM_VALIDATION');
  if ($requestChecksumValidation) {
    $CONFIG['objectstore']['arguments']['request_checksum_calculation'] = $requestChecksumValidation;
  }

  $responseChecksumValidation = getenv('OBJECTSTORE_S3_RESPONSE_CHECKSUM_VALIDATION');
  if ($responseChecksumValidation) {
    $CONFIG['objectstore']['arguments']['response_checksum_validation'] = $responseChecksumValidation;
  }
}
