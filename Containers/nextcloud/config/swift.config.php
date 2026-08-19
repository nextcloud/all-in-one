<?php
if (getenv('OBJECTSTORE_SWIFT_URL')) {
    $autocreate = getenv('OBJECTSTORE_SWIFT_AUTOCREATE');
  $CONFIG = array(
    'objectstore' => [
      'class' => 'OC\\Files\\ObjectStore\\Swift',
      'arguments' => [
        'autocreate' => $autocreate == true && strtolower($autocreate) !== 'false',
        'user' => [
          'name' => getenv('OBJECTSTORE_SWIFT_USER_NAME'),
          'password' => getenv('OBJECTSTORE_SWIFT_USER_PASSWORD'),
          'domain' => [
            'name' => (getenv('OBJECTSTORE_SWIFT_USER_DOMAIN')) ?: 'Default',
          ],
        ],
        'scope' => [
          'project' => [
            'name' => getenv('OBJECTSTORE_SWIFT_PROJECT_NAME'),
            'domain' => [
              'name' => (getenv('OBJECTSTORE_SWIFT_PROJECT_DOMAIN')) ?: 'Default',
            ],
          ],
        ],
        'serviceName' => (getenv('OBJECTSTORE_SWIFT_SERVICE_NAME')) ?: 'swift',
        'region' => getenv('OBJECTSTORE_SWIFT_REGION'),
        'url' => getenv('OBJECTSTORE_SWIFT_URL'),
        'bucket' => getenv('OBJECTSTORE_SWIFT_CONTAINER_NAME'),
      ]
    ]
  );
}
