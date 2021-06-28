<?php
return array
(
    'driver' => 'pdo_mysql',
    'dbname' => 'mp_test',
    'user' => 'test',
    'host' => 'localhost',
    'port' => getenv('MYSQL_PORT'),
    'password' => 'test',
);
