<?php
use midgard\portable\driver;
use midgard\portable\storage\connection;
use Doctrine\ORM\EntityManager;

require_once "vendor/autoload.php";

$schema_dirs = array
(
    // ADD YOUR SCHEMA DIR(S) HERE
);

$driver = new driver($schema_dirs, sys_get_temp_dir());

// CHANGE PARAMETERS AS REQUIRED:
$db_config = array
(
    'memory' => true,
    'driver' => 'pdo_sqlite',
);

connection::initialize($driver, $db_config);
$entityManager = connection::get_em();
