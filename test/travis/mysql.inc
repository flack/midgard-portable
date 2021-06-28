<?php
return array
(
    'driver' => 'pdo_mysql',
    'dbname' => 'mp_test',
    'user' => 'root',
    'host' => '127.0.0.1',
    'port' => getenv('MYSQL_PORT'),
    'password' => 'root',
);
