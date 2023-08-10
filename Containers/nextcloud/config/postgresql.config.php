<?php
$CONFIG = array (
    'dbuser' => 'oc_' . getenv('POSTGRES_USER'),
    'dbpassword' => getenv('POSTGRES_PASSWORD'),
    'db_name' => getenv('POSTGRES_DB'),
    'dbpersistent' => true,
);
