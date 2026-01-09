<?php
if (!getenv('REDIS_CLUSTER')) {
  if (getenv('REDIS_HOST')) {
    $CONFIG = array(
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
} else {
  if (getenv('REDIS_HOST')) {
    $CONFIG = array(
      'redis.cluster' => array(
        'password' => (string) getenv('REDIS_HOST_PASSWORD'),
        'timeout' => 0.0,
        'read_timeout' => 0.0,
        'failover_mode' => \RedisCluster::FAILOVER_ERROR,
        'seeds' => array(
          getenv('REDIS_HOST') . ':' . getenv('REDIS_PORT'),
        ),
      ),
    );

    if (getenv('REDIS_USER_AUTH') !== false) {
      $CONFIG['redis.cluster']['user'] = str_replace("&auth[]=", "", getenv('REDIS_USER_AUTH'));
    }
  }
}

if (getenv('REDIS_HOST')) {
  $CONFIG = array(
    'memcache.distributed' => '\OC\Memcache\Redis',
    'memcache.locking' => '\OC\Memcache\Redis',
  ),
}
