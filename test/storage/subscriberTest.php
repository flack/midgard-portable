<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard\portable\driver;
use midgard\portable\storage\connection;
use midgard\portable\storage\subscriber;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Event\SchemaCreateTableEventArgs;
use Doctrine\DBAL\Schema\Table;

class subscriberTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provider_onSchemaCreateTable
     */
    public function test_onSchemaCreateTable($columns, $expected)
    {
        $directories = array(TESTDIR . '__files/');
        $tmpdir = sys_get_temp_dir();
        $ns = uniqid(__CLASS__);
        $driver = new driver($directories, $tmpdir, $ns);
        include TESTDIR . DIRECTORY_SEPARATOR . 'bootstrap.php';
        $em = connection::get_em();
        $platform = $em->getConnection()->getDatabasePlatform();

        $table = new Table('dummy');
        $options = array();
        $args = new SchemaCreateTableEventArgs($table, $columns, $options, $platform);

        $subscriber = new subscriber;
        $subscriber->onSchemaCreateTable($args);

        $pf_name = strtolower($platform->getName());
        if (array_key_exists($pf_name, $expected))
        {
            $this->assertEquals($expected[$pf_name], $args->getSql());
        }
    }

    public function provider_onSchemaCreateTable()
    {
        return array
        (
            array
            (
                array
                (
                    'id' => array
                    (
                        'name' => "id",
                        'type' => Type::getType(Type::INTEGER),
                        'default' => null,
                        'notnull' => true,
                        'length' => null,
                        'precision' => 10,
                        'scale' => 0,
                        'fixed' => false,
                        'unsigned' => false,
                        'autoincrement' => true,
                        'columnDefinition' => null,
                        'comment' => null,
                        'version' => false,
                        'primary' => true
                    )
                ),
                array
                (
                    'sqlite' => array('CREATE TABLE dummy (id INTEGER PRIMARY KEY AUTOINCREMENT)'),
                )
            ),
            array
            (
                array
                (
                    'password' => array
                    (
                        'name' => "password",
                        'type' => Type::getType(Type::STRING),
                        'default' => null,
                        'notnull' => false,
                        'length' => 13,
                        'precision' => 10,
                        'scale' => 0,
                        'fixed' => false,
                        'unsigned' => false,
                        'autoincrement' => false,
                        'columnDefinition' => null,
                        'comment' => 'BINARY',
                        'version' => false,
                    )
                ),
                array
                (
                    'sqlite' => array("CREATE TABLE dummy (password VARCHAR(13) COLLATE BINARY DEFAULT NULL)"),
                    'mysql' => array("CREATE TABLE dummy (password VARCHAR(13) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL COMMENT 'BINARY')"),
                )
            ),
            array
            (
                array
                (
                    'settest' => array
                    (
                        'name' => "settest",
                        'type' => Type::getType(Type::STRING),
                        'default' => null,
                        'notnull' => false,
                        'length' => 13,
                        'precision' => 10,
                        'scale' => 0,
                        'fixed' => false,
                        'unsigned' => false,
                        'autoincrement' => false,
                        'columnDefinition' => null,
                        'comment' => "set('auth')",
                        'version' => false,
                    )
                ),
                array
                (
                    'mysql' => array("CREATE TABLE dummy (settest set('auth') DEFAULT NULL COMMENT 'set(''auth'')')"),
                )
            )
        );
    }
}