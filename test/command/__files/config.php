<?php
use midgard\portable\driver;
use midgard\portable\storage\connection;

$schema_dirs = [dirname(__DIR__, 2) . '/__files/membership/'];

$driver = new driver($schema_dirs, dirname(__DIR__, 2) . '/__output/commandTest/var', 'schemaCommandTest');

// CHANGE PARAMETERS AS REQUIRED:
$db_config = [
    'memory' => true,
    'driver' => 'pdo_sqlite',
];

connection::initialize($driver, $db_config, true);