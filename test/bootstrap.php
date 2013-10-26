<?php
use midgard\portable\storage\connection;

require_once dirname(__DIR__) . "/vendor/autoload.php";

$db_config = array
(
    'memory' => true,
    'driver' => 'pdo_sqlite'
);
connection::initialize($driver, $db_config);