<?php
if (getenv('REDIS_HOST')) {
  $CONFIG = array(
    'memcache.distributed' => '\OC\Memcache\Redis',
    'memcache.locking' => '\OC\Memcache\Redis',
    'redis' => array(
      'host' => getenv('REDIS_HOST'),
      'password' => (string) getenv('REDIS_HOST_PASSWORD'),
    ),
  );

  if (getenv('REDIS_PORT')) {
    $CONFIG['redis']['port'] = (int) getenv('REDIS_PORT');
  }

  if (getenv('REDIS_DB_INDEX')) {
    $CONFIG['redis']['dbindex'] = (int) getenv('REDIS_DB_INDEX');
  }

  if (getenv('REDIS_USER_AUTH') !== false) {
    $CONFIG['redis']['user'] = str_replace("&auth[]=", "", getenv('REDIS_USER_AUTH'));
  }
}
