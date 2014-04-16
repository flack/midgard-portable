<?php
use midgard\portable\storage\connection;

require_once dirname(__DIR__) . "/vendor/autoload.php";
$db = getenv('DB');
if (!empty($db))
{
    $db_config = require __DIR__ . DIRECTORY_SEPARATOR . $db . '.inc';
}
else
{
    $db_config = array
    (
        'memory' => true,
        'driver' => 'pdo_sqlite'
    );
}
connection::initialize($driver, $db_config);