<?php
if (getenv('REDIS_MODE' !== 'rediscluster')) {
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

  if (getenv('REDIS_USER_AUTH')) {
    $CONFIG['redis']['user'] = str_replace("&auth[]=", "", getenv('REDIS_USER_AUTH'));
  }
} else {
  $CONFIG = array(
    'memcache.distributed' => '\OC\Memcache\Redis',
    'memcache.locking' => '\OC\Memcache\Redis',
    'redis.cluster' => array(
      'password' => (string) getenv('REDIS_HOST_PASSWORD'),
      'timeout' => 0.0,
      'read_timeout' => 0.0,
      'failover_mode' => \RedisCluster::FAILOVER_ERROR,
      'seeds' => array_values(array_filter(array(
        (getenv('REDIS_HOST') && getenv('REDIS_PORT')) ? (getenv('REDIS_HOST') . ':' . (string)getenv('REDIS_PORT')) : null,
        (getenv('REDIS_HOST_2') && getenv('REDIS_PORT_2')) ? (getenv('REDIS_HOST_2') . ':' . (string)getenv('REDIS_PORT_2')) : null,
        (getenv('REDIS_HOST_3') && getenv('REDIS_PORT_3')) ? (getenv('REDIS_HOST_3') . ':' . (string)getenv('REDIS_PORT_3')) : null,
        (getenv('REDIS_HOST_4') && getenv('REDIS_PORT_4')) ? (getenv('REDIS_HOST_4') . ':' . (string)getenv('REDIS_PORT_4')) : null,
        (getenv('REDIS_HOST_5') && getenv('REDIS_PORT_5')) ? (getenv('REDIS_HOST_5') . ':' . (string)getenv('REDIS_PORT_5')) : null,
        (getenv('REDIS_HOST_6') && getenv('REDIS_PORT_6')) ? (getenv('REDIS_HOST_6') . ':' . (string)getenv('REDIS_PORT_6')) : null,
        (getenv('REDIS_HOST_7') && getenv('REDIS_PORT_7')) ? (getenv('REDIS_HOST_7') . ':' . (string)getenv('REDIS_PORT_7')) : null,
        (getenv('REDIS_HOST_8') && getenv('REDIS_PORT_8')) ? (getenv('REDIS_HOST_8') . ':' . (string)getenv('REDIS_PORT_8')) : null,
        (getenv('REDIS_HOST_9') && getenv('REDIS_PORT_9')) ? (getenv('REDIS_HOST_9') . ':' . (string)getenv('REDIS_PORT_9')) : null,
      ))),
    ),
  );

  if (getenv('REDIS_USER_AUTH')) {
    $CONFIG['redis.cluster']['user'] = str_replace("&auth[]=", "", getenv('REDIS_USER_AUTH'));
  }
}
