<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test\connection;

use midgard\portable\storage\connection;
use midgard\portable\storage\subscriber;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Event\SchemaCreateTableEventArgs;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\TestCase;
use midgard\portable\test\testcase as mgdcase;
use Doctrine\DBAL\Types\Types;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;

class subscriberTest extends TestCase
{
    /**
     * @dataProvider provider_onSchemaCreateTable
     */
    public function test_onSchemaCreateTable(array $columns, array $expected)
    {
        mgdcase::prepare_connection('', null, uniqid(__CLASS__));

        $em = connection::get_em();
        $platform = $em->getConnection()->getDatabasePlatform();

        $table = new Table('dummy');
        $options = [];
        $event = new SchemaCreateTableEventArgs($table, $columns, $options, $platform);

        $subscriber = new subscriber;
        $subscriber->onSchemaCreateTable($event);

        $found = false;
        foreach ($expected as $classname => $sql) {
            if ($platform instanceof $classname) {
                $found = true;
                $this->assertEquals($sql, $event->getSql());
            }
        }
        if (!$found) {
            $this->assertFalse($event->isDefaultPrevented());
        }
    }

    public static function provider_onSchemaCreateTable()
    {
        return [
            [
                [
                    'password' => [
                        'name' => "password",
                        'type' => Type::getType(Types::STRING),
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
                    ]
                ],
                [
                    SqlitePlatform::class => ["CREATE TABLE dummy (password VARCHAR(13) COLLATE BINARY DEFAULT NULL)"],
                    AbstractMySQLPlatform::class => ["CREATE TABLE dummy (password VARCHAR(13) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL COMMENT 'BINARY')"],
                ]
            ],
            [
                [
                    'settest' => [
                        'name' => "settest",
                        'type' => Type::getType(Types::STRING),
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
                    ]
                ],
                [
                    AbstractMySQLPlatform::class => ["CREATE TABLE dummy (settest set('auth') DEFAULT NULL COMMENT 'set(''auth'')')"],
                ]
            ]
        ];
    }
}
