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

  if (getenv('REDIS_HOST_PORT')) {
    $CONFIG['redis']['port'] = (int) getenv('REDIS_HOST_PORT');
  } elseif (getenv('REDIS_HOST')[0] != '/') {
    $CONFIG['redis']['port'] = 6379;
  }

  if (getenv('REDIS_DB_INDEX')) {
    $CONFIG['redis']['dbindex'] = (int) getenv('REDIS_DB_INDEX');
  }
}
