<?php
use midgard\portable\storage\driver;
use midgard\portable\storage\connection;
use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;

require_once "vendor/autoload.php";

$driver = new driver(array('/Users/sonic/git/openpsa/schemas/'), sys_get_temp_dir());

$dbfile = '/Users/sonic/db.sqlite';
if (file_exists($dbfile))
{
    unlink($dbfile);
}
$db_config = array
(
//            'memory' => true,
    'driver' => 'pdo_sqlite',
    'path' => $dbfile,
);

connection::initialize($driver, $db_config);
$entityManager = connection::get_em();